<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\DocumentTemplate;
use Doctrine\ORM\EntityManagerInterface;

class MasterTemplateCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return $this->entityManager->getRepository(DocumentTemplate::class)->findBy(['institute' => null]);
    }
}
