<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Session;
use App\Enum\SessionStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use App\Exception\ConflictHttpException;

class SessionSoftDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        /** @var Session $session */
        $session = $data;

        if ($session->getStatus() !== SessionStatusEnum::DRAFT) {
            throw new ConflictHttpException('Seule une session en statut DRAFT peut être supprimée.');
        }

        $session->setDeletedAt(new \DateTime());
        $this->entityManager->flush();
    }
}
