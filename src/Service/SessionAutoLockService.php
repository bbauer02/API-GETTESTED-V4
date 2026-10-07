<?php

namespace App\Service;

use App\Entity\Session;
use App\Enum\SessionStatusEnum;
use App\Repository\SessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Verrouillage automatique des sessions OPEN dont la date limite d'inscription est dépassée.
 * Utilisé par la commande app:sessions:auto-lock et de façon paresseuse par les providers.
 */
class SessionAutoLockService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SessionRepository $sessionRepository,
        private readonly WorkflowInterface $sessionLifecycleStateMachine,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function shouldLock(Session $session, ?\DateTimeInterface $now = null): bool
    {
        $now ??= new \DateTime();
        $limit = $session->getLimitDateSubscribe();

        return $session->getStatus() === SessionStatusEnum::OPEN
            && $limit !== null
            && $limit < $now;
    }

    /**
     * Verrouille la session si nécessaire. Retourne true si une transition a été appliquée.
     */
    public function lockIfExpired(Session $session, bool $flush = true): bool
    {
        if (!$this->shouldLock($session)) {
            return false;
        }

        if (!$this->sessionLifecycleStateMachine->can($session, 'lock')) {
            $this->logger->warning('Auto-lock impossible pour la session', ['sessionId' => (string) $session->getId()]);
            return false;
        }

        $this->sessionLifecycleStateMachine->apply($session, 'lock');
        $session->setLockedAutomaticallyAt(new \DateTime());

        if ($flush) {
            $this->entityManager->flush();
        }

        $this->logger->info('Session verrouillée automatiquement', ['sessionId' => (string) $session->getId()]);

        return true;
    }

    /**
     * @param iterable<Session> $sessions
     */
    public function lockExpiredAmong(iterable $sessions): int
    {
        $count = 0;
        foreach ($sessions as $session) {
            if ($this->lockIfExpired($session, false)) {
                $count++;
            }
        }
        if ($count > 0) {
            $this->entityManager->flush();
        }

        return $count;
    }

    /**
     * Toutes les sessions OPEN expirées de la base.
     */
    public function lockAllExpired(): int
    {
        $sessions = $this->sessionRepository->createQueryBuilder('s')
            ->where('s.status = :status')
            ->andWhere('s.limitDateSubscribe IS NOT NULL')
            ->andWhere('s.limitDateSubscribe < :now')
            ->setParameter('status', SessionStatusEnum::OPEN)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();

        return $this->lockExpiredAmong($sessions);
    }
}
