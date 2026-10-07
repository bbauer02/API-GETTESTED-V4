<?php

namespace App\Controller;

use App\Entity\EnrollmentSession;
use App\Enum\EnrollmentStatusEnum;
use App\Entity\Institute;
use App\Entity\Invoice;
use App\Entity\Session;
use App\Entity\User;
use App\Enum\BusinessTypeEnum;
use App\Enum\InvoiceTypeEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Indicateurs du back-office plateforme, calculés en base (les listes paginées n'en donnent qu'une page).
 */
class AdminStatsController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/admin/stats', name: 'api_admin_stats', methods: ['GET'])]
    #[IsGranted('ROLE_PLATFORM_ADMIN')]
    public function __invoke(): JsonResponse
    {
        $sessionsByStatus = [];
        foreach ($this->groupCount(Session::class, 'status', 'e.deletedAt IS NULL') as $row) {
            $sessionsByStatus[$row['status']->value] = (int) $row['total'];
        }

        // Factures d'inscription (hors commissions et avoirs) par statut : nombre et montant TTC
        $invoicesByStatus = [];
        $rows = $this->entityManager->createQueryBuilder()
            ->select('i.status AS status, COUNT(i.id) AS total, COALESCE(SUM(i.totalTTC), 0) AS amount')
            ->from(Invoice::class, 'i')
            ->where('i.invoiceType = :invoice')
            ->andWhere('i.businessType = :enrollment')
            ->setParameter('invoice', InvoiceTypeEnum::INVOICE)
            ->setParameter('enrollment', BusinessTypeEnum::ENROLLMENT)
            ->groupBy('i.status')
            ->getQuery()
            ->getArrayResult();
        foreach ($rows as $row) {
            $invoicesByStatus[$row['status']->value] = [
                'count' => (int) $row['total'],
                'amount' => round((float) $row['amount'], 2),
            ];
        }

        $commissions = (float) $this->entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(i.totalHT), 0)')
            ->from(Invoice::class, 'i')
            ->where('i.businessType = :commission')
            ->andWhere('i.status = :paid')
            ->setParameter('commission', BusinessTypeEnum::PLATFORM_COMMISSION)
            ->setParameter('paid', \App\Enum\InvoiceStatusEnum::PAID)
            ->getQuery()
            ->getSingleScalarResult();

        return new JsonResponse([
            'users' => $this->count(User::class),
            'institutes' => $this->count(Institute::class),
            'sessions' => array_sum($sessionsByStatus),
            'sessionsByStatus' => (object) $sessionsByStatus,
            'enrollments' => (int) $this->entityManager->getRepository(EnrollmentSession::class)->count(['status' => EnrollmentStatusEnum::ACTIVE]),
            'invoices' => array_sum(array_column($invoicesByStatus, 'count')),
            'invoicesByStatus' => (object) $invoicesByStatus,
            'commissionsHT' => round($commissions, 2),
        ]);
    }

    private function count(string $class): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from($class, 'e')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function groupCount(string $class, string $field, string $where): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select(sprintf('e.%s AS %s, COUNT(e.id) AS total', $field, $field))
            ->from($class, 'e')
            ->where($where)
            ->groupBy('e.' . $field)
            ->getQuery()
            ->getArrayResult();
    }
}
