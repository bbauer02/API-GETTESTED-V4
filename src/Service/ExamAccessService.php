<?php

namespace App\Service;

use App\Entity\EnrollmentExam;
use App\Entity\User;
use App\Enum\EnrollmentExamStatusEnum;
use App\Enum\SessionStatusEnum;
use App\Enum\SubjectStatusEnum;
use App\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Règles d'accès à une épreuve en ligne, appliquées par le serveur au démarrage,
 * à chaque réponse et à la fin :
 * - seul le candidat inscrit, avec une inscription réglée, sur une session verrouillée ou validée ;
 * - démarrage possible à partir de l'heure de l'épreuve, tant que le créneau n'est pas écoulé ;
 * - durée comptée à partir du premier démarrage (une reprise ne remet pas le chronomètre à zéro).
 */
class ExamAccessService
{
    /** Tolérance réseau accordée après l'échéance pour enregistrer une dernière réponse. */
    public const GRACE_SECONDS = 30;

    public function __construct(
        private readonly DocumentAccessService $documentAccessService,
    ) {
    }

    public function assertCandidate(EnrollmentExam $enrollmentExam, ?User $user): void
    {
        $enrollment = $enrollmentExam->getEnrollmentSession();
        if (!$user || !$enrollment || !$enrollment->getUser()?->getId()?->equals($user->getId())) {
            throw new AccessDeniedHttpException('Vous n\'êtes pas inscrit à cette épreuve.');
        }

        if ($enrollmentExam->getStatus() !== EnrollmentExamStatusEnum::REGISTERED) {
            throw new ConflictHttpException('Cette épreuve est terminée.');
        }
    }

    /** Vérifie que l'épreuve peut être démarrée (ou reprise) maintenant. */
    public function assertCanStart(EnrollmentExam $enrollmentExam, ?User $user, \DateTimeInterface $now = new \DateTime()): void
    {
        $this->assertCandidate($enrollmentExam, $user);

        $enrollment = $enrollmentExam->getEnrollmentSession();
        $session = $enrollment->getSession();
        $scheduledExam = $enrollmentExam->getScheduledExam();

        if (!in_array($session?->getStatus(), [SessionStatusEnum::LOCKED, SessionStatusEnum::VALIDATED], true)) {
            throw new ConflictHttpException('Cette épreuve n\'est pas ouverte : la session n\'est pas confirmée.');
        }

        if ($this->documentAccessService->hasUnpaidInvoice($enrollment)) {
            throw new ConflictHttpException('Réglez votre inscription pour accéder à l\'épreuve.');
        }

        $subject = $scheduledExam?->getSubject();
        if (!$subject || $subject->getStatus() !== SubjectStatusEnum::LOCKED) {
            throw new UnprocessableEntityHttpException('Le sujet de cette épreuve n\'est pas disponible.');
        }

        // Une reprise après rechargement reste possible jusqu'à l'échéance
        if ($enrollmentExam->getStartedAt() !== null) {
            if ($this->isExpired($enrollmentExam, $now)) {
                throw new ConflictHttpException('Le temps de l\'épreuve est écoulé.');
            }

            return;
        }

        $opensAt = $scheduledExam->getStartDate();
        if ($opensAt !== null && $now < $opensAt) {
            throw new ConflictHttpException(sprintf(
                'L\'épreuve ouvrira le %s.',
                (clone $opensAt)->setTimezone(new \DateTimeZone('Europe/Paris'))->format('d/m/Y à H:i')
            ));
        }

        $closesAt = $this->windowClosesAt($enrollmentExam);
        if ($closesAt !== null && $now >= $closesAt) {
            throw new ConflictHttpException('Le créneau de cette épreuve est terminé.');
        }
    }

    /** Vérifie qu'une réponse peut encore être enregistrée. */
    public function assertCanAnswer(EnrollmentExam $enrollmentExam, ?User $user, \DateTimeInterface $now = new \DateTime()): void
    {
        $this->assertCandidate($enrollmentExam, $user);

        if ($enrollmentExam->getStartedAt() === null) {
            throw new ConflictHttpException('L\'épreuve n\'a pas été démarrée.');
        }

        $deadline = $this->deadline($enrollmentExam);
        if ($deadline !== null && $now > (clone $deadline)->modify(sprintf('+%d seconds', self::GRACE_SECONDS))) {
            throw new ConflictHttpException('Le temps de l\'épreuve est écoulé : votre réponse n\'a pas été enregistrée.');
        }
    }

    /** Durée de l'épreuve en minutes (null = pas de limite). */
    public function durationMinutes(EnrollmentExam $enrollmentExam): ?int
    {
        $duration = $enrollmentExam->getScheduledExam()?->getExam()?->getDuration();

        return $duration && $duration > 0 ? $duration : null;
    }

    /** Échéance du candidat : début + durée. */
    public function deadline(EnrollmentExam $enrollmentExam): ?\DateTimeInterface
    {
        $startedAt = $enrollmentExam->getStartedAt();
        $duration = $this->durationMinutes($enrollmentExam);

        if ($startedAt === null || $duration === null) {
            return null;
        }

        return \DateTimeImmutable::createFromInterface($startedAt)->modify(sprintf('+%d minutes', $duration));
    }

    public function isExpired(EnrollmentExam $enrollmentExam, \DateTimeInterface $now = new \DateTime()): bool
    {
        $deadline = $this->deadline($enrollmentExam);

        return $deadline !== null && $now > (clone $deadline)->modify(sprintf('+%d seconds', self::GRACE_SECONDS));
    }

    /** Fin du créneau de démarrage : heure de l'épreuve + durée. */
    private function windowClosesAt(EnrollmentExam $enrollmentExam): ?\DateTimeInterface
    {
        $opensAt = $enrollmentExam->getScheduledExam()?->getStartDate();
        $duration = $this->durationMinutes($enrollmentExam);

        if ($opensAt === null || $duration === null) {
            return null;
        }

        return \DateTimeImmutable::createFromInterface($opensAt)->modify(sprintf('+%d minutes', $duration));
    }
}
