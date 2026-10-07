<?php

namespace App\Service;

use App\Entity\Embeddable\Counterparty;
use App\Entity\EnrollmentSession;
use App\Entity\Institute;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Entity\Payment;
use App\Enum\BusinessTypeEnum;
use App\Enum\InvoiceStatusEnum;
use App\Enum\InvoiceTypeEnum;
use App\Enum\OperationCategoryEnum;
use Doctrine\ORM\EntityManagerInterface;

class InvoiceService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StripeService $stripeService,
        private readonly string $platformName,
        private readonly string $platformAddress,
        private readonly string $platformCity,
        private readonly string $platformZipcode,
        private readonly string $platformCountryCode,
        private readonly string $platformVatNumber,
        private readonly string $platformSiren,
        private readonly string $platformSiret,
        private readonly string $platformLegalForm,
        private readonly string $platformShareCapital,
        private readonly string $platformRcsCity,
    ) {
    }

    /**
     * Crée la facture institut → candidat pour un enrollment.
     * Crée les lignes (1 par exam) et émet la facture (DRAFT → ISSUED).
     */
    public function createEnrollmentInvoice(EnrollmentSession $enrollment): Invoice
    {
        $session = $enrollment->getSession();
        $institute = $session->getInstitute();
        $user = $enrollment->getUser();

        $invoice = new Invoice();
        $invoice->setInstitute($institute);
        $invoice->setEnrollmentSession($enrollment);
        $invoice->setBusinessType(BusinessTypeEnum::ENROLLMENT);
        $invoice->setInvoiceType(InvoiceTypeEnum::INVOICE);
        $invoice->setStatus(InvoiceStatusEnum::DRAFT);

        // Seller = institut
        $seller = $this->buildCounterpartyFromInstitute($institute);
        $invoice->setSeller($seller);

        // Buyer = candidat
        $buyer = new Counterparty();
        $buyer->setName($user->getFirstname() . ' ' . $user->getLastname());
        if ($user->getAddress()) {
            $buyer->setAddress($user->getAddress()->getAddress1());
            $buyer->setCity($user->getAddress()->getCity());
            $buyer->setZipcode($user->getAddress()->getZipcode());
            $buyer->setCountryCode($user->getAddress()->getCountryCode());
        }
        $invoice->setBuyer($buyer);

        // Lignes de facture : 1 par épreuve à laquelle le candidat est inscrit
        foreach ($enrollment->getEnrollmentExams() as $enrollmentExam) {
            $scheduledExam = $enrollmentExam->getScheduledExam();
            $exam = $scheduledExam?->getExam();
            if (!$exam) {
                continue;
            }

            $line = new InvoiceLine();
            $line->setLabel($exam->getLabel());
            $line->setExam($exam);
            $line->setQuantity(1);

            // Prix personnalisé institut ou prix par défaut
            $pricing = $scheduledExam->getExamPricing();
            if ($pricing && $pricing->getPrice()->getAmount() !== null) {
                $line->setUnitPriceHT($pricing->getPrice()->getAmount());
                if ($pricing->getPrice()->getTva() !== null) {
                    $line->setTvaRate($pricing->getPrice()->getTva());
                }
            } elseif ($exam->getPrice()->getAmount() !== null) {
                $line->setUnitPriceHT($exam->getPrice()->getAmount());
                if ($exam->getPrice()->getTva() !== null) {
                    $line->setTvaRate($exam->getPrice()->getTva());
                }
            } else {
                $line->setUnitPriceHT(0);
            }

            $line->computeAmounts();
            $invoice->addLine($line);
            $this->entityManager->persist($line);
        }

        $this->entityManager->persist($invoice);

        // Émettre la facture (calculer totaux + numéroter)
        $this->issueInvoice($invoice);

        return $invoice;
    }

    /**
     * Crée la facture de commission plateforme → institut.
     * Appelée après un paiement réussi.
     */
    public function createCommissionInvoice(Invoice $enrollmentInvoice, Payment $payment): ?Invoice
    {
        $institute = $enrollmentInvoice->getInstitute();
        $stripeAccount = $institute->getStripeAccount();

        // Calculer la commission sur le montant HT de la facture d'enrollment
        $amountHT = $enrollmentInvoice->getTotalHT();
        $commissionRate = $stripeAccount?->getCommissionPercent();
        $effectiveRate = $commissionRate !== null ? (float) $commissionRate : $this->stripeService->getApplicationFeePercent();
        $commissionHT = round($amountHT * $effectiveRate / 100, 2);

        if ($commissionHT <= 0) {
            return null;
        }

        $commissionInvoice = new Invoice();
        $commissionInvoice->setInstitute($institute);
        $commissionInvoice->setEnrollmentSession($enrollmentInvoice->getEnrollmentSession());
        $commissionInvoice->setBusinessType(BusinessTypeEnum::PLATFORM_COMMISSION);
        $commissionInvoice->setInvoiceType(InvoiceTypeEnum::INVOICE);
        $commissionInvoice->setStatus(InvoiceStatusEnum::DRAFT);

        // Seller = plateforme GetTested
        $seller = $this->buildPlatformCounterparty();
        $commissionInvoice->setSeller($seller);

        // Buyer = institut
        $buyer = $this->buildCounterpartyFromInstitute($institute);
        $commissionInvoice->setBuyer($buyer);

        // Ligne unique : commission sur inscription
        $line = new InvoiceLine();
        $line->setLabel('Commission plateforme — Inscription session');
        $line->setDescription(sprintf(
            'Commission %.2f%% sur facture %s (base HT : %.2f €)',
            $effectiveRate,
            $enrollmentInvoice->getInvoiceNumber(),
            $amountHT
        ));
        $line->setQuantity(1);
        $line->setUnitPriceHT($commissionHT);
        $line->setTvaRate(20.0); // TVA sur prestation de service
        $line->computeAmounts();
        $commissionInvoice->addLine($line);
        $this->entityManager->persist($line);

        $this->entityManager->persist($commissionInvoice);

        // Émettre directement
        $this->issueInvoice($commissionInvoice);

        // La commission est prélevée automatiquement par Stripe, donc on marque PAID
        $commissionInvoice->setStatus(InvoiceStatusEnum::PAID);

        return $commissionInvoice;
    }

    /**
     * Émet une facture : calcule les totaux, génère le numéro, passe en ISSUED.
     */
    /**
     * Émet une facture : calcule les totaux, génère le numéro, passe en ISSUED.
     *
     * IMPORTANT : cette méthode doit être appelée dans une transaction ouverte par l'appelant.
     * Elle pose un verrou sur la table invoice pour garantir l'unicité du numéro.
     */
    public function issueInvoice(Invoice $invoice): void
    {
        if ($invoice->getLines()->isEmpty()) {
            return;
        }

        // Calculer les totaux
        $totalHT = 0;
        $totalTVA = 0;
        $totalTTC = 0;
        foreach ($invoice->getLines() as $line) {
            $totalHT += $line->getTotalHT();
            $totalTVA += $line->getTvaAmount();
            $totalTTC += $line->getTotalTTC();
        }
        $invoice->setTotalHT(round($totalHT, 2));
        $invoice->setTotalTVA(round($totalTVA, 2));
        $invoice->setTotalTTC(round($totalTTC, 2));

        // Verrou pessimiste pour garantir l'unicité du numéro de facture
        $conn = $this->entityManager->getConnection();
        $conn->executeStatement('LOCK TABLE invoice IN SHARE ROW EXCLUSIVE MODE');

        $invoice->setInvoiceNumber($this->generateInvoiceNumber($invoice));
        $invoice->setInvoiceDate(new \DateTime());
        $invoice->setStatus(InvoiceStatusEnum::ISSUED);

        $this->entityManager->flush();
    }

    private function generateInvoiceNumber(Invoice $invoice): string
    {
        $year = (new \DateTime())->format('Y');

        // Préfixe différent pour factures plateforme vs institut
        if ($invoice->getBusinessType() === BusinessTypeEnum::PLATFORM_COMMISSION) {
            $prefix = 'GT'; // GetTested platform
        } else {
            $prefix = $invoice->getInstitute()?->getId()
                ? strtoupper(substr($invoice->getInstitute()->getId()->toRfc4122(), 0, 8))
                : 'INV';
        }

        // Compter les factures existantes pour la séquence
        $qb = $this->entityManager->createQueryBuilder()
            ->select('COUNT(i.id)')
            ->from(Invoice::class, 'i')
            ->where('i.invoiceNumber IS NOT NULL')
            ->andWhere('i.invoiceNumber LIKE :yearPattern')
            ->setParameter('yearPattern', $prefix . '-' . $year . '-%');

        // Pour les factures institut, filtrer par institut
        if ($invoice->getBusinessType() !== BusinessTypeEnum::PLATFORM_COMMISSION) {
            $qb->andWhere('i.institute = :institute')
                ->setParameter('institute', $invoice->getInstitute());
        }

        $count = $qb->getQuery()->getSingleScalarResult();
        $sequence = str_pad((int) $count + 1, 5, '0', STR_PAD_LEFT);

        return "{$prefix}-{$year}-{$sequence}";
    }

    private function buildCounterpartyFromInstitute(Institute $institute): Counterparty
    {
        $counterparty = new Counterparty();
        $counterparty->setName($institute->getLabel());
        if ($institute->getAddress()) {
            $counterparty->setAddress($institute->getAddress()->getAddress1());
            $counterparty->setCity($institute->getAddress()->getCity());
            $counterparty->setZipcode($institute->getAddress()->getZipcode());
            $counterparty->setCountryCode($institute->getAddress()->getCountryCode());
        }
        $counterparty->setVatNumber($institute->getVatNumber());
        $counterparty->setSiren($institute->getSiren());
        $counterparty->setSiret($institute->getSiret());
        $counterparty->setLegalForm($institute->getLegalForm());
        $counterparty->setShareCapital($institute->getShareCapital());
        $counterparty->setRcsCity($institute->getRcsCity());
        $counterparty->setLogoUrl($institute->getLogo());

        return $counterparty;
    }

    private function buildPlatformCounterparty(): Counterparty
    {
        $counterparty = new Counterparty();
        $counterparty->setName($this->platformName);
        $counterparty->setAddress($this->platformAddress);
        $counterparty->setCity($this->platformCity);
        $counterparty->setZipcode($this->platformZipcode);
        $counterparty->setCountryCode($this->platformCountryCode);
        $counterparty->setVatNumber($this->platformVatNumber);
        $counterparty->setSiren($this->platformSiren);
        $counterparty->setSiret($this->platformSiret);
        $counterparty->setLegalForm($this->platformLegalForm);
        $counterparty->setShareCapital($this->platformShareCapital);
        $counterparty->setRcsCity($this->platformRcsCity);
        $counterparty->setLogoUrl('/logo/logo-single.png');

        return $counterparty;
    }
}
