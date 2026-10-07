<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Invoice;
use App\Enum\InvoiceStatusEnum;
use App\Service\InvoiceNumberGenerator;
use Doctrine\ORM\EntityManagerInterface;
use App\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class InvoiceIssueProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly InvoiceNumberGenerator $invoiceNumberGenerator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Invoice
    {
        /** @var Invoice $invoice */
        $invoice = $data;

        if ($invoice->getStatus() !== InvoiceStatusEnum::DRAFT) {
            throw new ConflictHttpException('Seule une facture en brouillon peut être émise.');
        }

        if ($invoice->getLines()->isEmpty()) {
            throw new UnprocessableEntityHttpException('La facture doit contenir au moins une ligne.');
        }

        // Calculer les totaux depuis les lignes
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

        // Assigner le numéro séquentiel avec verrou pour éviter les doublons
        $conn = $this->entityManager->getConnection();
        $conn->beginTransaction();
        try {
            // Verrou pessimiste sur la table invoice pour garantir l'unicité de la séquence
            $conn->executeStatement('LOCK TABLE invoice IN SHARE ROW EXCLUSIVE MODE');

            $invoice->setInvoiceNumber($this->invoiceNumberGenerator->next($invoice));

            $invoice->setInvoiceDate(new \DateTime());
            $invoice->setStatus(InvoiceStatusEnum::ISSUED);

            $this->entityManager->flush();
            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }

        return $invoice;
    }
}
