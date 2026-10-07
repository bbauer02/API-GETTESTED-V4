<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ExamCenter;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Persistance d'un centre d'examen : garantit un seul centre par défaut par institut.
 */
class ExamCenterPersistProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ExamCenter
    {
        /** @var ExamCenter $examCenter */
        $examCenter = $data;

        if ($examCenter->isDefault() && $examCenter->getInstitute()) {
            $others = $this->entityManager->getRepository(ExamCenter::class)->findBy([
                'institute' => $examCenter->getInstitute(),
                'isDefault' => true,
            ]);
            foreach ($others as $other) {
                if ($other !== $examCenter) {
                    $other->setIsDefault(false);
                }
            }
        }

        $this->entityManager->flush();

        return $examCenter;
    }
}
