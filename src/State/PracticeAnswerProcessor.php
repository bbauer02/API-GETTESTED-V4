<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\CandidateResponse;
use App\Entity\FillBlankQuestion;
use App\Entity\HighlightQuestion;
use App\Entity\MatchingQuestion;
use App\Entity\MCQQuestion;
use App\Entity\OrderingQuestion;
use App\Entity\PracticeSession;
use App\Entity\Question;
use App\Enum\PracticeSessionStatusEnum;
use App\Service\ResponseGraderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class PracticeAnswerProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly ResponseGraderService $graderService,
        private readonly RequestStack $requestStack,
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
            throw new UnprocessableEntityHttpException('Cette session est terminée.');
        }

        if ($session->getQuestionsAnswered() >= $session->getMaxQuestions()) {
            throw new UnprocessableEntityHttpException('Nombre maximum de questions atteint.');
        }

        // Extract answer data from request body
        $request = $this->requestStack->getCurrentRequest();
        $requestData = json_decode($request->getContent(), true) ?? [];

        $questionId = $requestData['questionId'] ?? null;
        $givenAnswer = $requestData['givenAnswer'] ?? [];
        $responseTimeMs = $requestData['responseTimeMs'] ?? 0;

        if (!$questionId) {
            throw new UnprocessableEntityHttpException('questionId est requis.');
        }

        // Find the question
        $question = $this->entityManager->getRepository(Question::class)->find($questionId);
        if (!$question) {
            throw new NotFoundHttpException('Question introuvable.');
        }

        // Verify question belongs to this assessment
        if ($question->getAssessment()->getId()->toRfc4122() !== $session->getAssessment()->getId()->toRfc4122()) {
            throw new UnprocessableEntityHttpException('Cette question n\'appartient pas à cet assessment.');
        }

        // Check if already answered in this session (idempotent)
        $existingResponse = $this->entityManager->getRepository(CandidateResponse::class)->findOneBy([
            'practiceSession' => $session,
            'question' => $question,
        ]);

        if ($existingResponse) {
            // Update existing response
            $existingResponse->setGivenAnswer($givenAnswer);
            $existingResponse->setResponseTimeMs($responseTimeMs);
            $existingResponse->setAnsweredAt(new \DateTime());
            $this->graderService->grade($existingResponse, $question);
            $this->entityManager->flush();

            return [
                'responseId' => $existingResponse->getId()->toRfc4122(),
                'score' => $existingResponse->getScore(),
                'maxPoints' => $question->getMaxPoints(),
                'status' => $existingResponse->getStatus()->value,
                'correctAnswer' => $this->getCorrectAnswer($question),
                'questionsAnswered' => $session->getQuestionsAnswered(),
            ];
        }

        // Create new response
        $response = new CandidateResponse();
        $response->setQuestion($question);
        $response->setPracticeSession($session);
        $response->setGivenAnswer($givenAnswer);
        $response->setResponseTimeMs($responseTimeMs);
        $response->setAnsweredAt(new \DateTime());

        // Grade it (practice: no subjectQuestion override)
        $this->graderService->grade($response, $question);

        // Increment questions answered
        $session->setQuestionsAnswered($session->getQuestionsAnswered() + 1);

        $this->entityManager->persist($response);
        $this->entityManager->flush();

        return [
            'responseId' => $response->getId()->toRfc4122(),
            'score' => $response->getScore(),
            'maxPoints' => $question->getMaxPoints(),
            'status' => $response->getStatus()->value,
            'correctAnswer' => $this->getCorrectAnswer($question),
            'questionsAnswered' => $session->getQuestionsAnswered(),
        ];
    }

    /**
     * Returns the correct answer for a question, so the candidate can learn.
     */
    private function getCorrectAnswer(Question $question): array
    {
        if ($question instanceof MCQQuestion) {
            $correctChoices = [];
            foreach ($question->getChoices() as $choice) {
                if ($choice->getWeight() > 0) {
                    $correctChoices[] = [
                        'id' => $choice->getId()->toRfc4122(),
                        'text' => $choice->getText(),
                        'weight' => $choice->getWeight(),
                    ];
                }
            }

            return ['type' => 'mcq', 'correctChoices' => $correctChoices];
        }

        if ($question instanceof FillBlankQuestion) {
            $slots = [];
            foreach ($question->getBlankSlots() as $slot) {
                $slots[] = [
                    'position' => $slot->getPosition(),
                    'acceptedAnswers' => $slot->getAcceptedAnswers(),
                ];
            }

            return ['type' => 'fill_blank', 'slots' => $slots];
        }

        if ($question instanceof HighlightQuestion) {
            $correctZones = [];
            foreach ($question->getHighlightZones() as $zone) {
                if ($zone->isCorrect()) {
                    $correctZones[] = [
                        'id' => $zone->getId()->toRfc4122(),
                        'startIndex' => $zone->getStartIndex(),
                        'endIndex' => $zone->getEndIndex(),
                    ];
                }
            }

            return ['type' => 'highlight', 'correctZones' => $correctZones];
        }

        if ($question instanceof OrderingQuestion) {
            $correctOrder = [];
            foreach ($question->getOrderingItems() as $item) {
                $correctOrder[] = [
                    'id' => $item->getId()->toRfc4122(),
                    'text' => $item->getText(),
                    'correctPosition' => $item->getCorrectPosition(),
                ];
            }
            // Sort by correct position
            usort($correctOrder, fn($a, $b) => $a['correctPosition'] <=> $b['correctPosition']);

            return ['type' => 'ordering', 'correctOrder' => $correctOrder];
        }

        if ($question instanceof MatchingQuestion) {
            $correctPairs = [];
            foreach ($question->getMatchingPairs() as $pair) {
                $correctPairs[] = [
                    'id' => $pair->getId()->toRfc4122(),
                    'leftText' => $pair->getLeftText(),
                    'rightText' => $pair->getRightText(),
                    'position' => $pair->getPosition(),
                ];
            }

            return ['type' => 'matching', 'correctPairs' => $correctPairs];
        }

        return ['type' => 'unknown'];
    }
}
