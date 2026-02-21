<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Session;
use App\Enum\SessionValidationEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

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

        if ($session->getValidation() !== SessionValidationEnum::DRAFT) {
            throw new UnprocessableEntityHttpException('Seule une session en statut DRAFT peut être supprimée.');
        }

        $session->setDeletedAt(new \DateTime());
        $this->entityManager->flush();
    }
}
