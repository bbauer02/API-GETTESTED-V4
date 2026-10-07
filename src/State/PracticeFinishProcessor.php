<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\AbilityEstimate;
use App\Entity\CandidateResponse;
use App\Entity\PracticeSession;
use App\Enum\AbilityEstimateSourceEnum;
use App\Enum\PracticeSessionStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class PracticeFinishProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $sessionId = $uriVariables['id'] ?? null;
        $session = $this->entityManager->getRepository(PracticeSession::class)->find($sessionId);

        if (!$session) {
            throw new NotFoundHttpException('Session introuvable.');
        }

        // Verify ownership
        $user = $this->security->getUser();
        if (!$session->getUser()->getId()->equals($user->getId())) {
            throw new AccessDeniedHttpException('Ce n\'est pas votre session.');
        }

        if ($session->getStatus() !== PracticeSessionStatusEnum::IN_PROGRESS) {
            throw new UnprocessableEntityHttpException('Cette session est déjà terminée.');
        }

        // Get all responses for this session
        $responses = $this->entityManager->getRepository(CandidateResponse::class)->findBy([
            'practiceSession' => $session,
        ]);

        $totalQuestions = count($responses);
        $totalScore = 0.0;
        $totalMaxPoints = 0.0;
        $skillScores = [];

        foreach ($responses as $response) {
            $question = $response->getQuestion();
            $totalScore += $response->getScore();
            $totalMaxPoints += $question->getMaxPoints();

            // Aggregate skill scores
            foreach ($question->getSkills() as $skill) {
                $skillId = $skill->getId()->toRfc4122();
                if (!isset($skillScores[$skillId])) {
                    $skillScores[$skillId] = [
                        'skillId' => $skillId,
                        'skillName' => $skill->getLabel(),
                        'totalScore' => 0.0,
                        'totalMaxPoints' => 0.0,
                        'questionsCount' => 0,
                    ];
                }
                $skillScores[$skillId]['totalScore'] += $response->getScore();
                $skillScores[$skillId]['totalMaxPoints'] += $question->getMaxPoints();
                $skillScores[$skillId]['questionsCount']++;
            }
        }

        // Calculate final theta as normalized score mapped to theta scale
        // Simple approach: (score / maxPoints) mapped to [-3, +3] range
        $normalizedScore = $totalMaxPoints > 0 ? $totalScore / $totalMaxPoints : 0.0;
        $finalTheta = round(($normalizedScore * 6) - 3, 3); // maps [0,1] to [-3, +3]

        // Calculate standard error (rough estimate based on number of questions)
        $finalStdError = $totalQuestions > 0 ? round(1.0 / sqrt($totalQuestions), 3) : 1.0;

        // Determine estimated level based on theta
        $estimatedLevel = $this->estimateLevelFromTheta($finalTheta);

        // Calculate skill percentages
        $skillScoresFormatted = [];
        foreach ($skillScores as $skillData) {
            $percentage = $skillData['totalMaxPoints'] > 0
                ? round(($skillData['totalScore'] / $skillData['totalMaxPoints']) * 100, 1)
                : 0.0;
            $skillScoresFormatted[] = [
                'skillId' => $skillData['skillId'],
                'skillName' => $skillData['skillName'],
                'score' => $skillData['totalScore'],
                'maxPoints' => $skillData['totalMaxPoints'],
                'percentage' => $percentage,
                'questionsCount' => $skillData['questionsCount'],
            ];
        }

        // Update session
        $session->setStatus(PracticeSessionStatusEnum::COMPLETED);
        $session->setFinishedAt(new \DateTime());
        $session->setTotalQuestions($totalQuestions);
        $session->setFinalTheta($finalTheta);
        $session->setFinalStdError($finalStdError);
        $session->setEstimatedLevel($estimatedLevel);
        $session->setSkillScores($skillScoresFormatted);

        // Create AbilityEstimate
        $abilityEstimate = new AbilityEstimate();
        $abilityEstimate->setUser($user);
        $abilityEstimate->setAssessment($session->getAssessment());
        $abilityEstimate->setLevel($session->getLevel());
        $abilityEstimate->setTheta($finalTheta);
        $abilityEstimate->setStdError($finalStdError);
        $abilityEstimate->setEstimatedLevel($estimatedLevel);
        $abilityEstimate->setSource(AbilityEstimateSourceEnum::PRACTICE);
        $abilityEstimate->setSkillBreakdown($skillScoresFormatted);
        $abilityEstimate->setEstimatedAt(new \DateTime());
        $abilityEstimate->setPracticeSession($session);

        $this->entityManager->persist($abilityEstimate);
        $this->entityManager->flush();

        return [
            'sessionId' => $session->getId()->toRfc4122(),
            'status' => $session->getStatus()->value,
            'totalQuestions' => $totalQuestions,
            'totalScore' => $totalScore,
            'totalMaxPoints' => $totalMaxPoints,
            'percentage' => $totalMaxPoints > 0 ? round(($totalScore / $totalMaxPoints) * 100, 1) : 0.0,
            'finalTheta' => $finalTheta,
            'finalStdError' => $finalStdError,
            'estimatedLevel' => $estimatedLevel,
            'skillScores' => $skillScoresFormatted,
            'startedAt' => $session->getStartedAt()->format('c'),
            'finishedAt' => $session->getFinishedAt()->format('c'),
        ];
    }

    /**
     * Maps theta to a CEFR-like level string.
     */
    private function estimateLevelFromTheta(float $theta): string
    {
        return match (true) {
            $theta < -2.0 => 'A1',
            $theta < -1.0 => 'A2',
            $theta < 0.0 => 'B1',
            $theta < 1.0 => 'B2',
            $theta < 2.0 => 'C1',
            default => 'C2',
        };
    }
}
