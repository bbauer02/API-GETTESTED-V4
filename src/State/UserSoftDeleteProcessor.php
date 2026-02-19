<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class UserSoftDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        /** @var User $user */
        $user = $data;
        $user->setDeletedAt(new \DateTime());
        $this->entityManager->flush();
    }
}
