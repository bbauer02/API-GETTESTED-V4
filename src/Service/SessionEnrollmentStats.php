<?php

namespace App\Service;

use App\Entity\EnrollmentSession;
use App\Entity\Session;
use App\Entity\User;
use App\Enum\EnrollmentStatusEnum;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Nombre d'inscrits actifs et « suis-je inscrit » pour les sessions d'une réponse.
 *
 * Au premier appel, les compteurs de toutes les sessions chargées dans la requête sont calculés
 * en une seule requête groupée (au lieu de deux requêtes par session dans une liste).
 * Réinitialisé entre deux requêtes (mode worker).
 */
class SessionEnrollmentStats implements ResetInterface
{
    /** @var array<string, int> */
    private array $counts = [];

    /** @var array<string, bool> */
    private array $mine = [];

    private ?string $viewerId = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function activeCount(Session $session, ?User $viewer): int
    {
        $this->load($session, $viewer);

        return $this->counts[(string) $session->getId()] ?? 0;
    }

    public function isEnrolled(Session $session, ?User $viewer): bool
    {
        if (!$viewer) {
            return false;
        }
        $this->load($session, $viewer);

        return $this->mine[(string) $session->getId()] ?? false;
    }

    public function reset(): void
    {
        $this->counts = [];
        $this->mine = [];
        $this->viewerId = null;
    }

    private function load(Session $session, ?User $viewer): void
    {
        $viewerId = $viewer?->getId()?->toRfc4122();
        if ($viewerId !== $this->viewerId) {
            $this->reset();
            $this->viewerId = $viewerId;
        }

        $id = (string) $session->getId();
        if (array_key_exists($id, $this->counts)) {
            return;
        }

        // Toutes les sessions présentes en mémoire et pas encore comptées
        $ids = [$id => $session->getId()];
        foreach ($this->entityManager->getUnitOfWork()->getIdentityMap()[Session::class] ?? [] as $loaded) {
            if ($loaded instanceof Session && $loaded->getId() && !array_key_exists((string) $loaded->getId(), $this->counts)) {
                $ids[(string) $loaded->getId()] = $loaded->getId();
            }
        }

        foreach (array_keys($ids) as $key) {
            $this->counts[$key] = 0;
            $this->mine[$key] = false;
        }

        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(e.session) AS sessionId', 'COUNT(e.id) AS total')
            ->addSelect('SUM(CASE WHEN e.user = :viewer THEN 1 ELSE 0 END) AS mine')
            ->from(EnrollmentSession::class, 'e')
            ->where('e.session IN (:sessions)')
            ->andWhere('e.status = :active')
            ->groupBy('e.session')
            ->setParameter('sessions', array_map(static fn ($uuid) => $uuid->toRfc4122(), array_values($ids)), ArrayParameterType::STRING)
            ->setParameter('active', EnrollmentStatusEnum::ACTIVE)
            // Visiteur anonyme : identifiant nul, jamais inscrit
            ->setParameter('viewer', $viewer?->getId()?->toRfc4122() ?? '00000000-0000-0000-0000-000000000000')
            ->getQuery();

        foreach ($rows->getArrayResult() as $row) {
            $key = (string) $row['sessionId'];
            $this->counts[$key] = (int) $row['total'];
            $this->mine[$key] = (int) $row['mine'] > 0;
        }
    }
}
