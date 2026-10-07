<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\CandidateResponse;
use App\Entity\FillBlankQuestion;
use App\Entity\HighlightQuestion;
use App\Entity\MatchingQuestion;
use App\Entity\MCQQuestion;
use App\Entity\OrderingQuestion;
use App\Entity\PracticeSession;
use App\Entity\Question;
use App\Enum\PracticeSessionStatusEnum;
use App\Enum\QuestionStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class PracticeNextQuestionProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $sessionId = $uriVariables['id'] ?? null;
        $session = $this->entityManager->getRepository(PracticeSession::class)->find($sessionId);

        if (!$session) {
            throw new NotFoundHttpException('Session introuvable.');
        }

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

        // Get already answered question IDs
        $responses = $this->entityManager->getRepository(CandidateResponse::class)->findBy([
            'practiceSession' => $session,
        ]);

        $answeredIds = array_map(fn($r) => $r->getQuestion()->getId()->toRfc4122(), $responses);

        // Determine difficulty direction from last 3 responses
        $recentResponses = array_slice($responses, -3);
        $recentCorrect = array_filter($recentResponses, fn($r) => $r->getScore() > 0);

        $difficultyBias = 'medium';
        if (count($recentResponses) >= 3) {
            if (count($recentCorrect) >= 3) {
                $difficultyBias = 'hard';
            } elseif (count($recentCorrect) === 0) {
                $difficultyBias = 'easy';
            }
        }

        // Get all eligible questions for this assessment
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('q')
            ->from(Question::class, 'q')
            ->where('q.assessment = :assessment')
            ->andWhere('q.status IN (:statuses)')
            ->setParameter('assessment', $session->getAssessment())
            ->setParameter('statuses', [QuestionStatusEnum::CALIBRATED->value, QuestionStatusEnum::PRETESTING->value]);

        if (!empty($answeredIds)) {
            $qb->andWhere('q.id NOT IN (:answered)')
                ->setParameter('answered', $answeredIds);
        }

        $eligible = $qb->getQuery()->getResult();

        if (empty($eligible)) {
            throw new UnprocessableEntityHttpException('Plus de questions disponibles.');
        }

        // Sort by difficulty bias
        // Use successRate as proxy: high successRate = easy, low = hard
        usort($eligible, function ($a, $b) use ($difficultyBias) {
            $rateA = $a->getSuccessRate() ?? 0.5;
            $rateB = $b->getSuccessRate() ?? 0.5;

            return match ($difficultyBias) {
                'easy' => $rateB <=> $rateA,   // highest success rate first
                'hard' => $rateA <=> $rateB,   // lowest success rate first
                default => 0,                   // keep original order (medium)
            };
        });

        // Favor skills not yet covered
        $coveredSkillIds = [];
        foreach ($responses as $response) {
            foreach ($response->getQuestion()->getSkills() as $skill) {
                $coveredSkillIds[$skill->getId()->toRfc4122()] = true;
            }
        }

        // Partition into uncovered-skill questions and covered-skill questions
        $uncoveredPool = [];
        $coveredPool = [];
        foreach ($eligible as $question) {
            $hasUncoveredSkill = false;
            foreach ($question->getSkills() as $skill) {
                if (!isset($coveredSkillIds[$skill->getId()->toRfc4122()])) {
                    $hasUncoveredSkill = true;
                    break;
                }
            }
            if ($hasUncoveredSkill) {
                $uncoveredPool[] = $question;
            } else {
                $coveredPool[] = $question;
            }
        }

        // Prefer uncovered skills, fallback to covered
        $pool = !empty($uncoveredPool) ? $uncoveredPool : $coveredPool;

        // Pick from top 5 randomly
        $topPool = array_slice($pool, 0, min(5, count($pool)));
        $selected = $topPool[array_rand($topPool)];

        return $this->buildQuestionResponse($selected, $session);
    }

    private function buildQuestionResponse(Question $question, PracticeSession $session): array
    {
        $data = [
            'practiceSessionId' => $session->getId()->toRfc4122(),
            'questionId' => $question->getId()->toRfc4122(),
            'questionsAnswered' => $session->getQuestionsAnswered(),
            'maxQuestions' => $session->getMaxQuestions(),
            'label' => $question->getLabel(),
            'text' => $question->getText(),
            'instruction' => $question->getInstruction(),
            'duration' => $question->getDuration(),
            'type' => $question->getType(),
            'maxPoints' => $question->getMaxPoints(),
            'medias' => [],
        ];

        foreach ($question->getMedias() as $media) {
            $data['medias'][] = [
                'type' => $media->getType()->value,
                'url' => $media->getUrl(),
                'description' => $media->getDescription(),
            ];
        }

        // Type-specific data WITHOUT answers
        $data = array_merge($data, $this->getTypeData($question));

        return $data;
    }

    private function getTypeData(Question $question): array
    {
        if ($question instanceof MCQQuestion) {
            $choices = $question->getChoices()->toArray();
            if ($question->isShuffleChoices()) {
                shuffle($choices);
            }

            return [
                'isMultipleAnswer' => $question->isMultipleAnswer(),
                'choices' => array_map(fn($c) => [
                    'id' => $c->getId()->toRfc4122(),
                    'text' => $c->getText(),
                ], $choices),
            ];
        }

        if ($question instanceof FillBlankQuestion) {
            return [
                'blankSymbol' => $question->getBlankSymbol(),
                'slots' => array_map(fn($s) => [
                    'id' => $s->getId()->toRfc4122(),
                    'position' => $s->getPosition(),
                ], $question->getBlankSlots()->toArray()),
            ];
        }

        if ($question instanceof HighlightQuestion) {
            return [
                'zones' => array_map(fn($z) => [
                    'id' => $z->getId()->toRfc4122(),
                    'startIndex' => $z->getStartIndex(),
                    'endIndex' => $z->getEndIndex(),
                ], $question->getHighlightZones()->toArray()),
            ];
        }

        if ($question instanceof OrderingQuestion) {
            $items = $question->getOrderingItems()->toArray();
            if ($question->isShuffleOnDisplay()) {
                shuffle($items);
            }

            return [
                'items' => array_map(fn($i) => [
                    'id' => $i->getId()->toRfc4122(),
                    'text' => $i->getText(),
                ], $items),
            ];
        }

        if ($question instanceof MatchingQuestion) {
            $pairs = $question->getMatchingPairs()->toArray();
            $left = array_map(fn($p) => ['id' => $p->getId()->toRfc4122(), 'text' => $p->getLeftText()], $pairs);
            $right = array_map(fn($p) => ['id' => $p->getId()->toRfc4122(), 'text' => $p->getRightText()], $pairs);
            if ($question->isShuffleOnDisplay()) {
                shuffle($right);
            }

            return ['leftItems' => $left, 'rightItems' => $right];
        }

        return [];
    }
}
