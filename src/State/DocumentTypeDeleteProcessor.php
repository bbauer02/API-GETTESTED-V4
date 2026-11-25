<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\DocumentType;
use App\Exception\ConflictHttpException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Suppression d'un type de document : refusée (409) tant que des modèles ou des documents
 * publiés l'utilisent, au lieu d'une erreur SQL 500 (clé étrangère).
 */
class DocumentTypeDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        /** @var DocumentType $documentType */
        $documentType = $data;

        $message = sprintf(
            'Impossible de supprimer le type « %s » : il est utilisé par des modèles de documents ou des documents publiés.',
            $documentType->getLabel(),
        );

        if (!$documentType->getDocumentTemplates()->isEmpty()) {
            throw new ConflictHttpException($message);
        }

        try {
            $this->entityManager->remove($documentType);
            $this->entityManager->flush();
        } catch (ForeignKeyConstraintViolationException) {
            throw new ConflictHttpException($message);
        }
    }
}
