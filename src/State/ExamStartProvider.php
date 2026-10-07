<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\EnrollmentExam;
use App\Entity\FillBlankQuestion;
use App\Entity\HighlightQuestion;
use App\Entity\MatchingQuestion;
use App\Entity\MCQQuestion;
use App\Entity\OrderingQuestion;
use App\Enum\EnrollmentExamStatusEnum;
use App\Enum\SubjectStatusEnum;
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
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $enrollmentExamId = $uriVariables['id'] ?? null;
        $enrollmentExam = $this->entityManager->getRepository(EnrollmentExam::class)->find($enrollmentExamId);

        if (!$enrollmentExam) {
            throw new NotFoundHttpException('EnrollmentExam introuvable.');
        }

        // Verify the current user is the enrolled candidate
        $currentUser = $this->security->getUser();
        $enrollmentSession = $enrollmentExam->getEnrollmentSession();

        if (!$enrollmentSession || !$enrollmentSession->getUser()->getId()->equals($currentUser->getId())) {
            throw new AccessDeniedHttpException('Vous n\'êtes pas inscrit à cet examen.');
        }

        // Verify enrollment status
        if ($enrollmentExam->getStatus() !== EnrollmentExamStatusEnum::REGISTERED) {
            throw new UnprocessableEntityHttpException('Cet examen a déjà été passé.');
        }

        // Get the scheduled exam and its subject
        $scheduledExam = $enrollmentExam->getScheduledExam();
        if (!$scheduledExam) {
            throw new NotFoundHttpException('Examen planifié introuvable.');
        }

        $subject = $scheduledExam->getSubject();

        if (!$subject || $subject->getStatus() !== SubjectStatusEnum::LOCKED) {
            throw new UnprocessableEntityHttpException('Le sujet n\'est pas disponible pour cet examen.');
        }

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
            $questionData = array_merge($questionData, $this->getTypeSpecificData($question));

            $questions[] = $questionData;
        }

        return [
            'enrollmentExamId' => $enrollmentExam->getId()->toRfc4122(),
            'subjectId' => $subject->getId()->toRfc4122(),
            'titre' => $subject->getTitre(),
            'totalMaxPoints' => $subject->getTotalMaxPoints(),
            'passingScore' => $subject->getPassingScore(),
            'questions' => $questions,
        ];
    }

    private function getTypeSpecificData($question): array
    {
        return match (true) {
            $question instanceof MCQQuestion => $this->getMcqData($question),
            $question instanceof FillBlankQuestion => $this->getFillBlankData($question),
            $question instanceof HighlightQuestion => $this->getHighlightData($question),
            $question instanceof OrderingQuestion => $this->getOrderingData($question),
            $question instanceof MatchingQuestion => $this->getMatchingData($question),
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

    private function getMatchingData(MatchingQuestion $question): array
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
            $rightItems[] = [
                'id' => $pair->getId()->toRfc4122(),
                'text' => $pair->getRightText(),
            ];
        }

        if ($question->isShuffleOnDisplay()) {
            shuffle($rightItems);
        }

        return [
            'leftItems' => $leftItems,
            'rightItems' => $rightItems,
        ];
    }
}
