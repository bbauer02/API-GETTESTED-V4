<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Embeddable\Counterparty;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Entity\Payment;
use App\Enum\BusinessTypeEnum;
use App\Enum\InvoiceStatusEnum;
use App\Enum\InvoiceTypeEnum;
use App\Enum\PaymentStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class PaymentRefundProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Payment
    {
        /** @var Payment $payment */
        $payment = $data;

        if ($payment->getStatus() !== PaymentStatusEnum::COMPLETED) {
            throw new UnprocessableEntityHttpException('Seul un paiement complété peut être remboursé.');
        }

        $payment->setStatus(PaymentStatusEnum::REFUNDED);

        // Créer un avoir (credit note) automatiquement
        $originalInvoice = $payment->getInvoice();
        if ($originalInvoice) {
            $conn = $this->entityManager->getConnection();
            $conn->beginTransaction();
            try {
                // P1 : Passer la facture originale en CANCELLED
                $originalInvoice->setStatus(InvoiceStatusEnum::CANCELLED);

                $creditNote = new Invoice();
                $creditNote->setInstitute($originalInvoice->getInstitute());
                $creditNote->setInvoiceType(InvoiceTypeEnum::CREDIT_NOTE);
                $creditNote->setBusinessType($originalInvoice->getBusinessType() ?? BusinessTypeEnum::ENROLLMENT);
                $creditNote->setOperationCategory($originalInvoice->getOperationCategory());
                $creditNote->setCreditedInvoice($originalInvoice);
                $creditNote->setEnrollmentSession($originalInvoice->getEnrollmentSession());
                $creditNote->setCurrency($originalInvoice->getCurrency());

                // Copier seller/buyer
                $origSeller = $originalInvoice->getSeller();
                $seller = new Counterparty();
                $seller->setName($origSeller->getName());
                $seller->setAddress($origSeller->getAddress());
                $seller->setCity($origSeller->getCity());
                $seller->setZipcode($origSeller->getZipcode());
                $seller->setCountryCode($origSeller->getCountryCode());
                $seller->setVatNumber($origSeller->getVatNumber());
                $seller->setSiren($origSeller->getSiren());
                $seller->setSiret($origSeller->getSiret());
                $seller->setLegalForm($origSeller->getLegalForm());
                $seller->setShareCapital($origSeller->getShareCapital());
                $seller->setRcsCity($origSeller->getRcsCity());
                $creditNote->setSeller($seller);

                $origBuyer = $originalInvoice->getBuyer();
                $buyer = new Counterparty();
                $buyer->setName($origBuyer->getName());
                $buyer->setAddress($origBuyer->getAddress());
                $buyer->setCity($origBuyer->getCity());
                $buyer->setZipcode($origBuyer->getZipcode());
                $buyer->setCountryCode($origBuyer->getCountryCode());
                $buyer->setVatNumber($origBuyer->getVatNumber());
                $creditNote->setBuyer($buyer);

                // P2/P3 : Reprendre les lignes de la facture originale en négatif avec TVA correcte
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

                // Assigner le numéro séquentiel et émettre directement
                $creditNote->setInvoiceNumber($this->generateInvoiceNumber($creditNote));
                $creditNote->setInvoiceDate(new \DateTime());
                $creditNote->setStatus(InvoiceStatusEnum::ISSUED);

                $this->entityManager->persist($creditNote);
                $this->entityManager->flush();
                $conn->commit();
            } catch (\Throwable $e) {
                $conn->rollBack();
                throw $e;
            }
        } else {
            $this->entityManager->flush();
        }

        return $payment;
    }

    private function generateInvoiceNumber(Invoice $invoice): string
    {
        $year = (new \DateTime())->format('Y');
        $prefix = $invoice->getInstitute()?->getId() ? substr($invoice->getInstitute()->getId()->toRfc4122(), 0, 8) : 'AV';
        $prefix = strtoupper($prefix);

        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(i.id)')
            ->from(Invoice::class, 'i')
            ->where('i.institute = :institute')
            ->andWhere('i.invoiceNumber IS NOT NULL')
            ->andWhere('i.invoiceNumber LIKE :yearPattern')
            ->setParameter('institute', $invoice->getInstitute())
            ->setParameter('yearPattern', '%-' . $year . '-%')
            ->getQuery()
            ->getSingleScalarResult();

        $sequence = str_pad((int) $count + 1, 5, '0', STR_PAD_LEFT);

        return "{$prefix}-{$year}-{$sequence}";
    }
}
