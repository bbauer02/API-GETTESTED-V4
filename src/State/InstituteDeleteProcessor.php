<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Institute;
use App\Exception\ConflictHttpException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Suppression définitive d'un institut, refusée dès qu'il porte un historique
 * (sessions ou factures) : la suppression en cascade effacerait des données comptables.
 */
class InstituteDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        /** @var Institute $institute */
        $institute = $data;

        $activeSessions = $institute->getSessions()->filter(fn ($session) => $session->getDeletedAt() === null);

        if (!$activeSessions->isEmpty() || !$institute->getInvoices()->isEmpty()) {
            throw new ConflictHttpException(sprintf(
                'Impossible de supprimer « %s » : l\'institut possède %d session(s) et %d facture(s). Les données de facturation doivent être conservées.',
                $institute->getLabel(),
                $activeSessions->count(),
                $institute->getInvoices()->count(),
            ));
        }

        try {
            $this->entityManager->remove($institute);
            $this->entityManager->flush();
        } catch (ForeignKeyConstraintViolationException) {
            throw new ConflictHttpException(sprintf(
                'Impossible de supprimer « %s » : des données y sont encore rattachées (questions, sujets, inscriptions…).',
                $institute->getLabel(),
            ));
        }
    }
}
