<?php

namespace App\Controller;

use App\Entity\Assessment;
use App\Entity\EnrollmentSession;
use App\Entity\Institute;
use App\Entity\Session;
use App\Enum\SessionStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Chiffres réels affichés sur la page d'accueil (aucune donnée personnelle).
 */
class PublicStatsController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/public/stats', name: 'api_public_stats', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $sessions = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(Session::class, 's')
            ->where('s.status IN (:statuses)')
            ->andWhere('s.deletedAt IS NULL')
            ->setParameter('statuses', [
                SessionStatusEnum::OPEN,
                SessionStatusEnum::LOCKED,
                SessionStatusEnum::VALIDATED,
            ])
            ->getQuery()
            ->getSingleScalarResult();

        $candidates = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(DISTINCT u.id)')
            ->from(EnrollmentSession::class, 'e')
            ->join('e.user', 'u')
            ->getQuery()
            ->getSingleScalarResult();

        $tests = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(Assessment::class, 'a')
            ->getQuery()
            ->getSingleScalarResult();

        $institutes = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(i.id)')
            ->from(Institute::class, 'i')
            ->getQuery()
            ->getSingleScalarResult();

        $response = new JsonResponse([
            'sessions' => $sessions,
            'candidates' => $candidates,
            'tests' => $tests,
            'institutes' => $institutes,
        ]);
        $response->setPublic();
        $response->setMaxAge(300);

        return $response;
    }
}
