<?php

namespace App\Service;

use App\Entity\Embeddable\Counterparty;
use App\Entity\EnrollmentSession;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Entity\Payment;
use App\Enum\BusinessTypeEnum;
use App\Enum\InvoiceStatusEnum;
use App\Enum\InvoiceTypeEnum;
use App\Enum\PaymentStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Remboursement d'une inscription : refund Stripe (si payment_intent connu),
 * Payment → REFUNDED, avoir (CREDIT_NOTE) et annulation des factures non payées.
 */
class RefundService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StripeService $stripeService,
        private readonly LoggerInterface $logger,
        private readonly InvoiceNumberGenerator $invoiceNumberGenerator,
    ) {
    }

    /**
     * Aperçu du remboursement d'une inscription.
     *
     * @return array{paidAmount: float, paymentCount: int, currency: string, invoicesToCancel: int}
     */
    public function previewEnrollment(EnrollmentSession $enrollment): array
    {
        $paidAmount = 0.0;
        $paymentCount = 0;
        $invoicesToCancel = 0;
        $currency = 'EUR';

        foreach ($this->enrollmentInvoices($enrollment) as $invoice) {
            $currency = $invoice->getCurrency() ?: $currency;
            if (in_array($invoice->getStatus(), [InvoiceStatusEnum::DRAFT, InvoiceStatusEnum::ISSUED, InvoiceStatusEnum::PAID], true)) {
                $invoicesToCancel++;
            }
            foreach ($invoice->getPayments() as $payment) {
                if ($payment->getStatus() === PaymentStatusEnum::COMPLETED) {
                    $paidAmount += (float) $payment->getAmount();
                    $paymentCount++;
                }
            }
        }

        return [
            'paidAmount' => round($paidAmount, 2),
            'paymentCount' => $paymentCount,
            'currency' => $currency,
            'invoicesToCancel' => $invoicesToCancel,
        ];
    }

    /**
     * Rembourse tous les paiements complétés de l'inscription et annule ses factures.
     * Les erreurs Stripe sont loggées et remontées dans "errors" sans bloquer le traitement.
     *
     * @return array{refundedAmount: float, refundedPayments: int, cancelledInvoices: int, errors: string[]}
     */
    public function refundEnrollment(EnrollmentSession $enrollment, string $reason = 'requested_by_customer'): array
    {
        $result = ['refundedAmount' => 0.0, 'refundedPayments' => 0, 'cancelledInvoices' => 0, 'errors' => []];

        foreach ($this->enrollmentInvoices($enrollment) as $invoice) {
            $hasCompletedPayment = false;

            foreach ($invoice->getPayments() as $payment) {
                if ($payment->getStatus() !== PaymentStatusEnum::COMPLETED) {
                    continue;
                }
                $hasCompletedPayment = true;

                $stripeId = $payment->getStripePaymentIntentId();
                if ($stripeId) {
                    try {
                        // Un paiement confirmé au retour de Checkout peut ne porter que l'ID de session (cs_*) :
                        // Stripe ne rembourse qu'un payment intent (pi_*).
                        if (str_starts_with($stripeId, 'cs_')) {
                            $intentId = $this->stripeService->retrieveCheckoutSession($stripeId)->payment_intent;
                            if (!$intentId) {
                                throw new \RuntimeException('Aucun payment intent associé à la session Checkout.');
                            }
                            $payment->setStripePaymentIntentId($intentId);
                            $stripeId = $intentId;
                        }

                        $this->stripeService->refundPaymentIntent($stripeId, $reason);
                    } catch (\Throwable $e) {
                        $message = sprintf(
                            'Remboursement Stripe impossible pour le paiement %s (%s) : %s',
                            $payment->getId(),
                            $stripeId,
                            $e->getMessage()
                        );
                        $this->logger->error($message, ['exception' => $e]);
                        $result['errors'][] = $message;

                        // Le paiement reste COMPLETED et la facture PAID : rien n'a été remboursé
                        continue;
                    }
                }

                $this->refundPayment($payment);
                $result['refundedAmount'] += (float) $payment->getAmount();
                $result['refundedPayments']++;
                $result['cancelledInvoices']++;
            }

            if (!$hasCompletedPayment
                && in_array($invoice->getStatus(), [InvoiceStatusEnum::DRAFT, InvoiceStatusEnum::ISSUED], true)
            ) {
                $invoice->setStatus(InvoiceStatusEnum::CANCELLED);
                $result['cancelledInvoices']++;
            }
        }

        $this->entityManager->flush();
        $result['refundedAmount'] = round($result['refundedAmount'], 2);

        return $result;
    }

    /**
     * Payment COMPLETED → REFUNDED, facture d'origine → CANCELLED, création d'un avoir ISSUED.
     * (Logique extraite de PaymentRefundProcessor.)
     */
    public function refundPayment(Payment $payment): ?Invoice
    {
        $payment->setStatus(PaymentStatusEnum::REFUNDED);

        $originalInvoice = $payment->getInvoice();
        if (!$originalInvoice) {
            $this->entityManager->flush();
            return null;
        }

        $conn = $this->entityManager->getConnection();
        $conn->beginTransaction();
        try {
            $originalInvoice->setStatus(InvoiceStatusEnum::CANCELLED);

            $creditNote = new Invoice();
            $creditNote->setInstitute($originalInvoice->getInstitute());
            $creditNote->setInvoiceType(InvoiceTypeEnum::CREDIT_NOTE);
            $creditNote->setBusinessType($originalInvoice->getBusinessType() ?? BusinessTypeEnum::ENROLLMENT);
            $creditNote->setOperationCategory($originalInvoice->getOperationCategory());
            $creditNote->setCreditedInvoice($originalInvoice);
            $creditNote->setEnrollmentSession($originalInvoice->getEnrollmentSession());
            $creditNote->setCurrency($originalInvoice->getCurrency());
            $creditNote->setSeller($this->copyCounterparty($originalInvoice->getSeller()));
            $creditNote->setBuyer($this->copyCounterparty($originalInvoice->getBuyer()));

            $totalHT = 0;
            $totalTVA = 0;
            $totalTTC = 0;
            foreach ($originalInvoice->getLines() as $originalLine) {
                $line = new InvoiceLine();
                $line->setLabel('Avoir — ' . $originalLine->getLabel());
                $line->setDescription($originalLine->getDescription());
                $line->setExam($originalLine->getExam());
                $line->setQuantity($originalLine->getQuantity());
                $line->setUnitPriceHT(-abs($originalLine->getUnitPriceHT()));
                $line->setTvaRate($originalLine->getTvaRate());
                $line->computeAmounts();
                $creditNote->addLine($line);
                $this->entityManager->persist($line);

                $totalHT += $line->getTotalHT();
                $totalTVA += $line->getTvaAmount();
                $totalTTC += $line->getTotalTTC();
            }

            $creditNote->setTotalHT(round($totalHT, 2));
            $creditNote->setTotalTVA(round($totalTVA, 2));
            $creditNote->setTotalTTC(round($totalTTC, 2));

            // Verrou pessimiste pour numérotation séquentielle
            $conn->executeStatement('LOCK TABLE invoice IN SHARE ROW EXCLUSIVE MODE');

            $creditNote->setInvoiceNumber($this->invoiceNumberGenerator->next($creditNote));
            $creditNote->setInvoiceDate(new \DateTime());
            $creditNote->setStatus(InvoiceStatusEnum::ISSUED);

            $this->entityManager->persist($creditNote);
            $this->entityManager->flush();
            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }

        return $creditNote;
    }

    /**
     * @return Invoice[] factures d'inscription (hors commissions plateforme et hors avoirs)
     */
    public function enrollmentInvoices(EnrollmentSession $enrollment): array
    {
        $invoices = [];
        foreach ($enrollment->getInvoices() as $invoice) {
            if ($invoice->getInvoiceType() !== InvoiceTypeEnum::INVOICE) {
                continue;
            }
            if ($invoice->getBusinessType() === BusinessTypeEnum::PLATFORM_COMMISSION) {
                continue;
            }
            $invoices[] = $invoice;
        }

        return $invoices;
    }

    private function copyCounterparty(Counterparty $source): Counterparty
    {
        $copy = new Counterparty();
        $copy->setName($source->getName());
        $copy->setAddress($source->getAddress());
        $copy->setCity($source->getCity());
        $copy->setZipcode($source->getZipcode());
        $copy->setCountryCode($source->getCountryCode());
        $copy->setVatNumber($source->getVatNumber());
        $copy->setSiren($source->getSiren());
        $copy->setSiret($source->getSiret());
        $copy->setLegalForm($source->getLegalForm());
        $copy->setShareCapital($source->getShareCapital());
        $copy->setRcsCity($source->getRcsCity());
        $copy->setLogoUrl($source->getLogoUrl());

        return $copy;
    }

}
