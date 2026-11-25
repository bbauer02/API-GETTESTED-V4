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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

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

        // Le montant ne peut pas dépasser le reste à payer (paiements déjà encaissés déduits)
        $amount = round((float) $payment->getAmount(), 2);
        if ($amount <= 0) {
            throw new UnprocessableEntityHttpException('Le montant du paiement doit être supérieur à zéro.');
        }
        $remaining = round($invoice->getTotalTTC() - $this->completedTotal($invoice), 2);
        if ($amount > $remaining) {
            throw new UnprocessableEntityHttpException(sprintf(
                'Le montant dépasse le reste à payer (%s %s).',
                number_format(max(0, $remaining), 2, ',', ' '),
                $invoice->getCurrency(),
            ));
        }

        $payment->setInvoice($invoice);
        $payment->setStatus(PaymentStatusEnum::PENDING);
        $payment->setDate(new \DateTime());

        // ?complete=1 : paiement reçu enregistré et encaissé en une seule opération (back-office),
        // pour ne pas laisser de paiement « en attente » orphelin si une 2e requête échouait.
        // Mêmes droits que PATCH /payments/{id}/complete (admin plateforme / admin de l'institut).
        $request = $context['request'] ?? null;
        $completeNow = $request instanceof Request && $request->query->getBoolean('complete');

        $this->entityManager->persist($payment);

        if ($completeNow) {
            $payment->setStatus(PaymentStatusEnum::COMPLETED);
            $invoice->addPayment($payment);
            if ($this->completedTotal($invoice) >= round($invoice->getTotalTTC(), 2)) {
                $invoice->setStatus(InvoiceStatusEnum::PAID);
            }
        }

        $this->entityManager->flush();

        return $payment;
    }

    private function completedTotal(Invoice $invoice): float
    {
        $total = 0.0;
        foreach ($invoice->getPayments() as $p) {
            if ($p->getStatus() === PaymentStatusEnum::COMPLETED) {
                $total += (float) $p->getAmount();
            }
        }

        return round($total, 2);
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
            if ($membership->isActive()
                && $membership->getUser()?->getId()?->equals($user->getId())
                && $membership->getRole() === InstituteRoleEnum::ADMIN
            ) {
                return true;
            }
        }

        return false;
    }
}
