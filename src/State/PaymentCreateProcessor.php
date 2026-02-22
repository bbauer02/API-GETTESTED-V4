<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\InvoiceStatusEnum;
use App\Enum\PaymentStatusEnum;
use App\Enum\PlatformRoleEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use App\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PaymentCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Payment
    {
        $invoiceId = $uriVariables['invoiceId'] ?? null;
        $invoice = $this->entityManager->getRepository(Invoice::class)->find($invoiceId);

        if (!$invoice) {
            throw new NotFoundHttpException('Facture introuvable.');
        }

        if ($invoice->getStatus() !== InvoiceStatusEnum::ISSUED) {
            throw new ConflictHttpException('Les paiements ne peuvent être créés que pour une facture émise.');
        }

        /** @var User $currentUser */
        $currentUser = $this->security->getUser();

        if (!$this->canCreate($currentUser, $invoice)) {
            throw new AccessDeniedHttpException('Vous n\'avez pas les droits pour créer un paiement.');
        }

        /** @var Payment $payment */
        $payment = $data;
        $payment->setInvoice($invoice);
        $payment->setStatus(PaymentStatusEnum::PENDING);
        $payment->setDate(new \DateTime());

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return $payment;
    }

    private function canCreate(User $user, Invoice $invoice): bool
    {
        if ($user->getPlatformRole() === PlatformRoleEnum::ADMIN) {
            return true;
        }

        $institute = $invoice->getInstitute();
        if (!$institute) {
            return false;
        }

        foreach ($institute->getMemberships() as $membership) {
            if ($membership->getUser()?->getId()?->equals($user->getId())
                && $membership->getRole() === InstituteRoleEnum::ADMIN
            ) {
                return true;
            }
        }

        return false;
    }
}
