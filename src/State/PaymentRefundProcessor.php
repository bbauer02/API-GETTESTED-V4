<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Payment;
use App\Enum\PaymentStatusEnum;
use App\Exception\ConflictHttpException;
use App\Service\RefundService;

/**
 * PATCH /api/payments/{id}/refund — remboursement manuel (comptable) :
 * Payment → REFUNDED + avoir (CREDIT_NOTE). Aucun appel Stripe ici : le refund Stripe est
 * déclenché par l'annulation de session / d'inscription (RefundService::refundEnrollment).
 */
class PaymentRefundProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly RefundService $refundService,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Payment
    {
        /** @var Payment $payment */
        $payment = $data;

        if ($payment->getStatus() !== PaymentStatusEnum::COMPLETED) {
            throw new ConflictHttpException('Seul un paiement complété peut être remboursé.');
        }

        $this->refundService->refundPayment($payment);

        return $payment;
    }
}
