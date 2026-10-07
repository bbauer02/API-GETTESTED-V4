<?php

namespace App\Service;

use App\Entity\CandidateResponse;
use App\Entity\EnrollmentExam;
use App\Enum\EnrollmentExamStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Fin d'une épreuve en ligne : note calculée à partir des réponses enregistrées,
 * statut PASSED / FAILED, statistiques des questions.
 * Utilisé par le candidat (bouton « Terminer ») et par la clôture automatique des épreuves expirées.
 */
class ExamFinisher
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array résultat présenté au candidat (note, statistiques, détail par compétence)
     */
    public function finish(EnrollmentExam $enrollmentExam): array
    {
        // Get the subject via ScheduledExam -> Subject (OneToOne)
        $scheduledExam = $enrollmentExam->getScheduledExam();
        $subject = $scheduledExam?->getSubject();

        if (!$subject) {
            throw new UnprocessableEntityHttpException('Sujet introuvable.');
        }

        // Get all responses for this exam
        $responses = $this->entityManager->getRepository(CandidateResponse::class)->findBy([
            'enrollmentExam' => $enrollmentExam,
        ]);

        // Build response map: subjectQuestionId => CandidateResponse
        $responseMap = [];
        foreach ($responses as $response) {
            $sqId = $response->getSubjectQuestion()?->getId()->toRfc4122();
            if ($sqId) {
                $responseMap[$sqId] = $response;
            }
        }

        // Calculate total score (only non-seed questions) and skill breakdown
        $totalScore = 0.0;
        $skillScores = []; // skill label => [earned, max]

        foreach ($subject->getSubjectQuestions() as $sq) {
            if ($sq->isSeed()) {
                continue; // seed questions don't count toward score
            }

            $question = $sq->getQuestion();
            $maxPoints = $sq->getPointsOverride() ?? $question->getMaxPoints();
            $sqId = $sq->getId()->toRfc4122();
            $earned = isset($responseMap[$sqId]) ? $responseMap[$sqId]->getScore() : 0.0;

            $totalScore += $earned;

            // Skill breakdown
            foreach ($question->getSkills() as $skill) {
                $skillLabel = $skill->getLabel();
                if (!isset($skillScores[$skillLabel])) {
                    $skillScores[$skillLabel] = ['earned' => 0.0, 'max' => 0.0];
                }
                $skillScores[$skillLabel]['earned'] += $earned;
                $skillScores[$skillLabel]['max'] += $maxPoints;
            }
        }

        $totalScore = round($totalScore, 2);
        $passingScore = $subject->getPassingScore() ?? 0;
        $passed = $totalScore >= $passingScore;

        // Update enrollment exam
        $enrollmentExam->setFinalScore((int) round($totalScore));
        $enrollmentExam->setStatus($passed ? EnrollmentExamStatusEnum::PASSED : EnrollmentExamStatusEnum::FAILED);

        // Update question statistics
        foreach ($responses as $response) {
            $question = $response->getQuestion();
            $administered = $question->getTimesAdministered() + 1;
            $question->setTimesAdministered($administered);

            // Update success rate (running average)
            $subjectQuestion = $response->getSubjectQuestion();
            $maxPointsForQ = $subjectQuestion?->getPointsOverride() ?? $question->getMaxPoints();
            $wasCorrect = $response->getScore() >= $maxPointsForQ;
            $oldRate = $question->getSuccessRate() ?? 0.0;
            $newRate = $oldRate + ($wasCorrect ? 1.0 - $oldRate : 0.0 - $oldRate) / $administered;
            $question->setSuccessRate(round($newRate, 4));

            // Update avg response time
            $oldAvg = $question->getAvgResponseTimeMs() ?? 0;
            $newAvg = $oldAvg + ($response->getResponseTimeMs() - $oldAvg) / $administered;
            $question->setAvgResponseTimeMs((int) round($newAvg));
        }

        // Build skill breakdown for response
        $skillBreakdown = [];
        foreach ($skillScores as $label => $scores) {
            $skillBreakdown[$label] = $scores['max'] > 0
                ? round($scores['earned'] / $scores['max'], 4)
                : 0;
        }

        $this->entityManager->flush();

        // Statistiques affichées au candidat (hors questions d'étalonnage)
        $scoredQuestions = array_filter($subject->getSubjectQuestions()->toArray(), fn ($sq) => !$sq->isSeed());
        $answered = $correct = 0;
        foreach ($scoredQuestions as $sq) {
            $response = $responseMap[$sq->getId()->toRfc4122()] ?? null;
            if (!$response) {
                continue;
            }
            $answered++;
            if ($response->getScore() >= ($sq->getPointsOverride() ?? $sq->getQuestion()->getMaxPoints())) {
                $correct++;
            }
        }

        return [
            'enrollmentExamId' => $enrollmentExam->getId()->toRfc4122(),
            'totalQuestions' => count($scoredQuestions),
            'answeredQuestions' => $answered,
            'correctAnswers' => $correct,
            'wrongAnswers' => $answered - $correct,
            'totalScore' => $totalScore,
            'maxScore' => $subject->getTotalMaxPoints(),
            'passingScore' => $passingScore,
            'passed' => $passed,
            'status' => $enrollmentExam->getStatus()->value,
            'skillBreakdown' => $skillBreakdown,
        ];
    }
}
