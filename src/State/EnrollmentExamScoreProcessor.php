<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\EnrollmentExam;
use App\Enum\EnrollmentExamStatusEnum;
use App\Enum\SessionStatusEnum;
use App\Exception\ConflictHttpException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * PATCH /enrollment-exams/{id}/score — saisie du résultat d'une épreuve.
 *
 * - ABSENT : le candidat ne s'est pas présenté, la note est effacée.
 * - Avec une note et un seuil de réussite : PASSED / FAILED calculé.
 * - Sans seuil : le statut PASSED / FAILED saisi manuellement est conservé.
 */
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

        // Les résultats se saisissent une fois les inscriptions closes (session verrouillée ou validée)
        $sessionStatus = $enrollmentExam->getEnrollmentSession()?->getSession()?->getStatus();
        if (!in_array($sessionStatus, [SessionStatusEnum::LOCKED, SessionStatusEnum::VALIDATED], true)) {
            throw new ConflictHttpException('Les résultats ne peuvent être saisis que pour une session verrouillée ou validée.');
        }

        $finalScore = $enrollmentExam->getFinalScore();
        $exam = $enrollmentExam->getScheduledExam()?->getExam();

        if ($enrollmentExam->getStatus() === EnrollmentExamStatusEnum::ABSENT) {
            $enrollmentExam->setFinalScore(null);
        } else {
            if ($finalScore !== null && $finalScore < 0) {
                throw new UnprocessableEntityHttpException('La note ne peut pas être négative.');
            }

            $successScore = $exam?->getSuccessScore();
            if ($successScore !== null && $finalScore !== null) {
                $enrollmentExam->setStatus(
                    $finalScore >= $successScore
                        ? EnrollmentExamStatusEnum::PASSED
                        : EnrollmentExamStatusEnum::FAILED
                );
            } elseif ($finalScore === null && $enrollmentExam->getStatus() !== EnrollmentExamStatusEnum::REGISTERED
                && $successScore !== null
            ) {
                // Note effacée : l'épreuve redevient « à évaluer »
                $enrollmentExam->setStatus(EnrollmentExamStatusEnum::REGISTERED);
            }
        }

        $this->entityManager->flush();

        return $enrollmentExam;
    }
}
