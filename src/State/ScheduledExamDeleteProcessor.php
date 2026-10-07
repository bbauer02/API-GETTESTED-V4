<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\EnrollmentExam;
use App\Entity\ScheduledExam;
use App\Exception\ConflictHttpException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * DELETE /api/scheduled-exams/{id}
 * 409 si des inscriptions (EnrollmentExam) ou un sujet sont rattachés à l'épreuve.
 */
class ScheduledExamDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        /** @var ScheduledExam $scheduledExam */
        $scheduledExam = $data;

        $enrollmentCount = $this->entityManager->getRepository(EnrollmentExam::class)
            ->count(['scheduledExam' => $scheduledExam]);
        if ($enrollmentCount > 0) {
            throw new ConflictHttpException(sprintf(
                "Impossible de supprimer cette épreuve : %d inscription(s) y sont rattachée(s). Annulez ou transférez d'abord les inscriptions.",
                $enrollmentCount
            ));
        }

        if ($scheduledExam->getSubject() !== null) {
            throw new ConflictHttpException("Impossible de supprimer cette épreuve : un sujet d'examen y est rattaché.");
        }

        $scheduledExam->getExaminators()->clear();

        $this->entityManager->remove($scheduledExam);
        $this->entityManager->flush();

        return null;
    }
}
