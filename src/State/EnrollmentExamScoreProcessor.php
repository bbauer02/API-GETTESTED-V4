<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\EnrollmentExam;
use App\Enum\EnrollmentExamStatusEnum;
use Doctrine\ORM\EntityManagerInterface;

class EnrollmentExamScoreProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): EnrollmentExam
    {
        /** @var EnrollmentExam $enrollmentExam */
        $enrollmentExam = $data;

        $finalScore = $enrollmentExam->getFinalScore();
        $successScore = $enrollmentExam->getScheduledExam()?->getExam()?->getSuccessScore();

        if ($successScore !== null && $finalScore !== null) {
            $enrollmentExam->setStatus(
                $finalScore >= $successScore
                    ? EnrollmentExamStatusEnum::PASSED
                    : EnrollmentExamStatusEnum::FAILED
            );
        }

        $this->entityManager->flush();

        return $enrollmentExam;
    }
}
