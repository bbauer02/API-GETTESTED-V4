<?php

namespace App\Service;

use App\Entity\Institute;
use App\Entity\Invoice;
use App\Enum\BusinessTypeEnum;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Numérotation des factures et avoirs : « PRÉFIXE-AAAA-NNNNN », séquence annuelle par émetteur.
 *
 * - Plateforme (commissions) : préfixe « GT ».
 * - Institut : 8 derniers caractères de son UUID. Les premiers caractères d'un UUID v7 sont un
 *   horodatage, identiques pour des instituts créés à la même période : ils provoquaient des
 *   numéros en double entre instituts.
 *
 * Les factures et avoirs d'un même émetteur partagent la séquence (numérotation continue).
 */
class InvoiceNumberGenerator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function next(Invoice $invoice): string
    {
        $year = (new \DateTime())->format('Y');
        $prefix = $this->prefix($invoice);
        $pattern = sprintf('%s-%s-', $prefix, $year);

        $last = $this->entityManager->createQueryBuilder()
            ->select('MAX(i.invoiceNumber)')
            ->from(Invoice::class, 'i')
            ->where('i.invoiceNumber LIKE :pattern')
            ->setParameter('pattern', $pattern . '%')
            ->getQuery()
            ->getSingleScalarResult();

        $sequence = $last ? ((int) substr($last, strlen($pattern))) + 1 : 1;

        return $pattern . str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

    public static function institutePrefix(Institute $institute): string
    {
        return strtoupper(substr(str_replace('-', '', $institute->getId()->toRfc4122()), -8));
    }

    private function prefix(Invoice $invoice): string
    {
        if ($invoice->getBusinessType() === BusinessTypeEnum::PLATFORM_COMMISSION) {
            return 'GT';
        }

        $institute = $invoice->getInstitute();

        return $institute?->getId() ? self::institutePrefix($institute) : 'INV';
    }
}
