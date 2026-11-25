<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Question;
use App\Enum\QuestionStatusEnum;
use App\Exception\ConflictHttpException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Workflow de statut des questions (opérations dédiées, sécurité QUESTION_EDIT) :
 * - POST /questions/{id}/pretest : DRAFT -> PRETESTING ;
 * - POST /questions/{id}/retire  : DRAFT / PRETESTING / CALIBRATED -> RETIRED (retiredAt, retiredReason).
 * Le passage en CALIBRATED reste du ressort de la calibration.
 */
class QuestionStatusProcessor implements ProcessorInterface
{
    private const MAX_REASON_LENGTH = 255;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Question
    {
        /** @var Question $question */
        $question = $data;

        match ($operation->getName()) {
            'question_pretest' => $this->pretest($question),
            'question_retire' => $this->retire($question, $context['request'] ?? null),
            default => throw new \LogicException('Opération de statut inconnue.'),
        };

        $this->entityManager->flush();

        return $question;
    }

    private function pretest(Question $question): void
    {
        if ($question->getStatus() !== QuestionStatusEnum::DRAFT) {
            throw new ConflictHttpException('Seule une question en brouillon peut passer en pré-test.');
        }

        $question->setStatus(QuestionStatusEnum::PRETESTING);
    }

    private function retire(Question $question, ?Request $request): void
    {
        if ($question->getStatus() === QuestionStatusEnum::RETIRED) {
            throw new ConflictHttpException('Cette question est déjà retirée.');
        }

        $reason = null;
        if ($request !== null && $request->getContent() !== '') {
            try {
                $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $payload = [];
            }
            if (is_array($payload) && is_string($payload['reason'] ?? null)) {
                $reason = trim($payload['reason']) !== '' ? mb_substr(trim($payload['reason']), 0, self::MAX_REASON_LENGTH) : null;
            }
        }

        $question->setStatus(QuestionStatusEnum::RETIRED);
        $question->setRetiredAt(new \DateTime());
        $question->setRetiredReason($reason);
    }
}
