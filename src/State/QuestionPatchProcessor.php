<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Question;
use App\Enum\QuestionStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Modification d'une question.
 * Une question CALIBRATED ne peut changer que de libellé et de consigne : toute autre
 * modification (énoncé, points, niveau, choix, trous, zones…) invaliderait sa calibration.
 */
class QuestionPatchProcessor implements ProcessorInterface
{
    private const CALIBRATED_EDITABLE_FIELDS = ['label', 'instruction', 'updatedAt'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Question
    {
        /** @var Question $question */
        $question = $data;

        /** @var Question|null $previous */
        $previous = $context['previous_data'] ?? null;

        if ($previous instanceof Question && $previous->getStatus() === QuestionStatusEnum::CALIBRATED
            && $this->hasContentChanges($question)
        ) {
            throw new UnprocessableEntityHttpException(
                'Cette question est calibrée : seuls le libellé et la consigne peuvent être modifiés. Retirez-la et créez-en une nouvelle pour changer son contenu.'
            );
        }

        $this->entityManager->persist($question);
        $this->entityManager->flush();

        return $question;
    }

    private function hasContentChanges(Question $question): bool
    {
        $uow = $this->entityManager->getUnitOfWork();
        $uow->computeChangeSets();

        $changedFields = array_keys($uow->getEntityChangeSet($question));
        if (array_diff($changedFields, self::CALIBRATED_EDITABLE_FIELDS) !== []) {
            return true;
        }

        // Éléments rattachés (choix, trous, zones, paires, médias…) ajoutés, modifiés ou supprimés
        $related = array_merge(
            $uow->getScheduledEntityInsertions(),
            $uow->getScheduledEntityUpdates(),
            $uow->getScheduledEntityDeletions(),
        );
        foreach ($related as $entity) {
            if ($entity !== $question && method_exists($entity, 'getQuestion') && $entity->getQuestion() === $question) {
                return true;
            }
        }

        foreach (array_merge($uow->getScheduledCollectionUpdates(), $uow->getScheduledCollectionDeletions()) as $collection) {
            if ($collection->getOwner() === $question) {
                return true;
            }
        }

        return false;
    }
}
