<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Payment;
use App\Enum\PaymentStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class PaymentFailProcessor implements ProcessorInterface
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
            throw new UnprocessableEntityHttpException('Seul un paiement en attente peut être marqué comme échoué.');
        }

        $payment->setStatus(PaymentStatusEnum::FAILED);

        $this->entityManager->flush();

        return $payment;
    }
}
