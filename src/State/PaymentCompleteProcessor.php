<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Payment;
use App\Enum\InvoiceStatusEnum;
use App\Enum\PaymentStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class PaymentCompleteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Payment
    {
        /** @var Payment $payment */
        $payment = $data;

        if ($payment->getStatus() !== PaymentStatusEnum::PENDING) {
            throw new UnprocessableEntityHttpException('Seul un paiement en attente peut être complété.');
        }

        $payment->setStatus(PaymentStatusEnum::COMPLETED);

        // Vérifier si le total des paiements COMPLETED >= totalTTC
        $invoice = $payment->getInvoice();
        if ($invoice) {
            $totalPaid = 0;
            foreach ($invoice->getPayments() as $p) {
                if ($p->getStatus() === PaymentStatusEnum::COMPLETED) {
                    $totalPaid += $p->getAmount();
                }
            }

            if ($totalPaid >= $invoice->getTotalTTC()) {
                $invoice->setStatus(InvoiceStatusEnum::PAID);
            }
        }

        $this->entityManager->flush();

        return $payment;
    }
}
