<?php

namespace App\EventListener;

use App\Entity\EnrollmentSession;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;

/**
 * Generates unique reference numbers:
 * - User.candidateNumber: "C" + 6 digits (e.g. C000042)
 * - EnrollmentSession.referenceNumber: YYYY-NNNNN (e.g. 2026-00001)
 */
#[AsDoctrineListener(event: Events::prePersist)]
class ReferenceNumberListener
{
    /** Last candidate number generated in this process (handles several persists before a single flush). */
    private int $lastCandidateNumber = 0;

    /** Last enrollment reference generated in this process, keyed by year prefix. */
    private array $lastEnrollmentNumber = [];

    public function prePersist(PrePersistEventArgs $event): void
    {
        $entity = $event->getObject();
        $em = $event->getObjectManager();

        if ($entity instanceof User && $entity->getCandidateNumber() === null) {
            $conn = $em->getConnection();
            $result = $conn->fetchOne('SELECT MAX(candidate_number) FROM "user" WHERE candidate_number IS NOT NULL');

            if ($result === null || $result === false) {
                $next = 1;
            } else {
                // Extract numeric part after "C"
                $next = ((int) substr($result, 1)) + 1;
            }

            $next = max($next, $this->lastCandidateNumber + 1);
            $this->lastCandidateNumber = $next;

            $entity->setCandidateNumber(sprintf('C%06d', $next));
        }

        if ($entity instanceof EnrollmentSession && $entity->getReferenceNumber() === null) {
            $conn = $em->getConnection();
            $year = (new \DateTime())->format('Y');
            $prefix = $year . '-';

            $result = $conn->fetchOne(
                'SELECT MAX(reference_number) FROM enrollment_session WHERE reference_number LIKE :prefix',
                ['prefix' => $prefix . '%']
            );

            if ($result === null || $result === false) {
                $next = 1;
            } else {
                // Extract numeric part after "YYYY-"
                $next = ((int) substr($result, 5)) + 1;
            }

            $next = max($next, ($this->lastEnrollmentNumber[$prefix] ?? 0) + 1);
            $this->lastEnrollmentNumber[$prefix] = $next;

            $entity->setReferenceNumber(sprintf('%s%05d', $prefix, $next));
        }
    }
}
