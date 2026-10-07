<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\EnrollmentExam;
use App\Entity\CandidateResponse;
use App\Entity\FillBlankQuestion;
use App\Entity\HighlightQuestion;
use App\Entity\MatchingQuestion;
use App\Entity\MCQQuestion;
use App\Entity\OrderingQuestion;
use App\Enum\EnrollmentExamStatusEnum;
use App\Enum\SubjectStatusEnum;
use App\Exception\ConflictHttpException;
use App\Service\ExamAccessService;
use App\Service\ExamFinisher;
use App\Service\ResponseGraderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ExamStartProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly ExamAccessService $examAccessService,
        private readonly ResponseGraderService $graderService,
        private readonly ExamFinisher $examFinisher,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $enrollmentExamId = $uriVariables['id'] ?? null;
        $enrollmentExam = $this->entityManager->getRepository(EnrollmentExam::class)->find($enrollmentExamId);

        if (!$enrollmentExam) {
            throw new NotFoundHttpException('EnrollmentExam introuvable.');
        }

        // Épreuve commencée puis abandonnée : temps écoulé, elle est notée avec les réponses enregistrées
        if ($enrollmentExam->getStartedAt() !== null && $this->examAccessService->isExpired($enrollmentExam)) {
            $this->examAccessService->assertCandidate($enrollmentExam, $this->security->getUser());
            $this->examFinisher->finish($enrollmentExam);

            throw new ConflictHttpException('Le temps de l\'épreuve est écoulé : elle a été notée avec les réponses enregistrées.');
        }

        // Candidat, statut de session, paiement, créneau horaire : contrôlés par le serveur
        $this->examAccessService->assertCanStart($enrollmentExam, $this->security->getUser());

        $scheduledExam = $enrollmentExam->getScheduledExam();

        // Premier démarrage : le chronomètre part maintenant (une reprise conserve l'heure initiale)
        if ($enrollmentExam->getStartedAt() === null) {
            $enrollmentExam->setStartedAt(new \DateTime());
            $this->entityManager->flush();
        }

        $subject = $scheduledExam->getSubject();

        // Build the response: questions in order, WITHOUT correct answers
        $questions = [];
        foreach ($subject->getSubjectQuestions() as $sq) {
            $question = $sq->getQuestion();
            $questionData = [
                'subjectQuestionId' => $sq->getId()->toRfc4122(),
                'questionId' => $question->getId()->toRfc4122(),
                'position' => $sq->getPosition(),
                'isSeed' => $sq->isSeed(),
                'points' => $sq->getPointsOverride() ?? $question->getMaxPoints(),
                'label' => $question->getLabel(),
                'text' => $question->getText(),
                'instruction' => $question->getInstruction(),
                'duration' => $question->getDuration(),
                'type' => $question->getType(),
                'medias' => [],
            ];

            // Add medias
            foreach ($question->getMedias() as $media) {
                $questionData['medias'][] = [
                    'id' => $media->getId()->toRfc4122(),
                    'type' => $media->getType()->value,
                    'url' => $media->getUrl(),
                    'description' => $media->getDescription(),
                ];
            }

            // Add type-specific data WITHOUT revealing answers
            $questionData = array_merge($questionData, $this->getTypeSpecificData($question, $enrollmentExam->getId()->toRfc4122()));

            $questions[] = $questionData;
        }

        // Réponses déjà enregistrées (reprise après un rechargement de page)
        $savedAnswers = [];
        $responses = $this->entityManager->getRepository(CandidateResponse::class)->findBy(['enrollmentExam' => $enrollmentExam]);
        foreach ($responses as $response) {
            $sqId = $response->getSubjectQuestion()?->getId()?->toRfc4122();
            if ($sqId) {
                $savedAnswers[$sqId] = $response->getGivenAnswer();
            }
        }

        $deadline = $this->examAccessService->deadline($enrollmentExam);

        return [
            'enrollmentExamId' => $enrollmentExam->getId()->toRfc4122(),
            'subjectId' => $subject->getId()->toRfc4122(),
            'titre' => $subject->getTitre(),
            'examName' => $scheduledExam->getExam()?->getLabel(),
            'assessmentName' => $enrollmentExam->getEnrollmentSession()?->getSession()?->getAssessment()?->getLabel(),
            'totalMaxPoints' => $subject->getTotalMaxPoints(),
            'passingScore' => $subject->getPassingScore(),
            'durationMinutes' => $this->examAccessService->durationMinutes($enrollmentExam),
            'startedAt' => $enrollmentExam->getStartedAt()?->format(\DateTimeInterface::ATOM),
            'endsAt' => $deadline?->format(\DateTimeInterface::ATOM),
            'serverNow' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'savedAnswers' => (object) $savedAnswers,
            'questions' => $questions,
        ];
    }

    private function getTypeSpecificData($question, string $scopeId): array
    {
        return match (true) {
            $question instanceof MCQQuestion => $this->getMcqData($question),
            $question instanceof FillBlankQuestion => $this->getFillBlankData($question),
            $question instanceof HighlightQuestion => $this->getHighlightData($question),
            $question instanceof OrderingQuestion => $this->getOrderingData($question),
            $question instanceof MatchingQuestion => $this->getMatchingData($question, $scopeId),
            default => [],
        };
    }

    private function getMcqData(MCQQuestion $question): array
    {
        $choices = [];
        $choicesList = $question->getChoices()->toArray();

        if ($question->isShuffleChoices()) {
            shuffle($choicesList);
        }

        foreach ($choicesList as $choice) {
            $choices[] = [
                'id' => $choice->getId()->toRfc4122(),
                'text' => $choice->getText(),
                // NOTE: weight is NOT included (don't reveal correct answers)
            ];
        }

        return [
            'isMultipleAnswer' => $question->isMultipleAnswer(),
            'choices' => $choices,
        ];
    }

    private function getFillBlankData(FillBlankQuestion $question): array
    {
        $slots = [];
        foreach ($question->getBlankSlots() as $slot) {
            $slots[] = [
                'id' => $slot->getId()->toRfc4122(),
                'position' => $slot->getPosition(),
                // NOTE: acceptedAnswers NOT included
            ];
        }

        return [
            'blankSymbol' => $question->getBlankSymbol(),
            'slots' => $slots,
        ];
    }

    private function getHighlightData(HighlightQuestion $question): array
    {
        $zones = [];
        foreach ($question->getHighlightZones() as $zone) {
            $zones[] = [
                'id' => $zone->getId()->toRfc4122(),
                'startIndex' => $zone->getStartIndex(),
                'endIndex' => $zone->getEndIndex(),
                // NOTE: isCorrect NOT included
            ];
        }

        return ['zones' => $zones];
    }

    private function getOrderingData(OrderingQuestion $question): array
    {
        $items = $question->getOrderingItems()->toArray();

        if ($question->isShuffleOnDisplay()) {
            shuffle($items);
        }

        $result = [];
        foreach ($items as $item) {
            $result[] = [
                'id' => $item->getId()->toRfc4122(),
                'text' => $item->getText(),
                // NOTE: correctPosition NOT included
            ];
        }

        return ['items' => $result];
    }

    private function getMatchingData(MatchingQuestion $question, string $scopeId): array
    {
        $pairs = $question->getMatchingPairs()->toArray();

        // Shuffle right column independently
        $leftItems = [];
        $rightItems = [];

        foreach ($pairs as $pair) {
            $leftItems[] = [
                'id' => $pair->getId()->toRfc4122(),
                'text' => $pair->getLeftText(),
            ];
            // Jeton opaque : ne révèle pas l'élément de gauche correspondant
            $rightItems[] = [
                'id' => $this->graderService->matchingToken($pair->getId()->toRfc4122(), $scopeId),
                'text' => $pair->getRightText(),
            ];
        }

        // Ordre toujours mélangé, sinon la position trahirait la solution
        shuffle($rightItems);

        return [
            'leftItems' => $leftItems,
            'rightItems' => $rightItems,
        ];
    }
}
