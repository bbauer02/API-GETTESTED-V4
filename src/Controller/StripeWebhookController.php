<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\StripeAccount;
use App\Enum\InvoiceStatusEnum;
use App\Enum\PaymentStatusEnum;
use App\Service\InvoiceService;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StripeWebhookController extends AbstractController
{
    public function __construct(
        private readonly StripeService $stripeService,
        private readonly InvoiceService $invoiceService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/api/webhooks/stripe', name: 'api_stripe_webhook', methods: ['POST'])]
    public function handleWebhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('Stripe-Signature');

        if (!$sigHeader) {
            return new JsonResponse(['error' => 'Missing Stripe-Signature header'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $event = $this->stripeService->constructWebhookEvent($payload, $sigHeader);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            $this->logger->warning('Stripe webhook signature verification failed', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Invalid signature'], Response::HTTP_BAD_REQUEST);
        }

        $this->logger->info('Stripe webhook received', ['type' => $event->type, 'id' => $event->id]);

        match ($event->type) {
            'account.updated' => $this->handleAccountUpdated($event),
            'checkout.session.completed' => $this->handleCheckoutSessionCompleted($event),
            'checkout.session.expired' => $this->handleCheckoutSessionExpired($event),
            'payment_intent.payment_failed' => $this->handlePaymentIntentFailed($event),
            default => $this->logger->debug('Unhandled Stripe event type', ['type' => $event->type]),
        };

        return new JsonResponse(['received' => true]);
    }

    private function handleAccountUpdated(\Stripe\Event $event): void
    {
        /** @var \Stripe\Account $account */
        $account = $event->data->object;

        $stripeAccountRepo = $this->entityManager->getRepository(StripeAccount::class);
        $stripeAccount = $stripeAccountRepo->findOneBy(['stripeId' => $account->id]);

        if (!$stripeAccount) {
            $this->logger->warning('Stripe account not found for account.updated', ['stripeId' => $account->id]);
            return;
        }

        $stripeAccount->setChargesEnabled($account->charges_enabled);
        $stripeAccount->setPayoutsEnabled($account->payouts_enabled);
        $stripeAccount->setOnboardingComplete($account->details_submitted);
        $stripeAccount->setIsActivated($account->charges_enabled && $account->payouts_enabled);

        $this->entityManager->flush();

        $this->logger->info('Stripe account updated', [
            'stripeId' => $account->id,
            'chargesEnabled' => $account->charges_enabled,
            'payoutsEnabled' => $account->payouts_enabled,
        ]);
    }

    /**
     * Paiement Stripe réussi.
     * 1. Marquer le Payment comme COMPLETED
     * 2. Si total payé >= totalTTC → Invoice passe en PAID
     * 3. Créer la facture de commission plateforme → institut
     */
    private function handleCheckoutSessionCompleted(\Stripe\Event $event): void
    {
        /** @var \Stripe\Checkout\Session $session */
        $session = $event->data->object;
        $metadata = $session->metadata?->toArray() ?? [];

        $invoiceId = $metadata['invoiceId'] ?? null;
        $paymentId = $metadata['paymentId'] ?? null;

        if (!$invoiceId || !$paymentId) {
            $this->logger->warning('checkout.session.completed: missing metadata', ['metadata' => $metadata]);
            return;
        }

        $invoice = $this->entityManager->getRepository(Invoice::class)->find($invoiceId);
        $payment = $this->entityManager->getRepository(Payment::class)->find($paymentId);

        if (!$invoice || !$payment) {
            $this->logger->warning('checkout.session.completed: invoice or payment not found', [
                'invoiceId' => $invoiceId,
                'paymentId' => $paymentId,
            ]);
            return;
        }

        // Éviter le double traitement
        if ($payment->getStatus() === PaymentStatusEnum::COMPLETED) {
            $this->logger->info('checkout.session.completed: payment already completed', ['paymentId' => $paymentId]);
            return;
        }

        // 1. Marquer le paiement comme complété
        $payment->setStatus(PaymentStatusEnum::COMPLETED);

        // Stocker le payment_intent_id réel si disponible
        if ($session->payment_intent) {
            $payment->setStripePaymentIntentId($session->payment_intent);
        }

        // 2. Vérifier si la facture est entièrement payée
        $totalPaid = 0;
        foreach ($invoice->getPayments() as $p) {
            if ($p->getStatus() === PaymentStatusEnum::COMPLETED) {
                $totalPaid += $p->getAmount();
            }
        }

        // Une facture déjà payée (ex. confirmée par verify-payment) ne génère pas de seconde commission
        if ($invoice->getStatus() !== InvoiceStatusEnum::PAID && $totalPaid >= $invoice->getTotalTTC()) {
            $invoice->setStatus(InvoiceStatusEnum::PAID);

            // 3. Créer la facture de commission plateforme → institut
            $this->invoiceService->createCommissionInvoice($invoice, $payment);

            $this->logger->info('Invoice fully paid, commission invoice created', [
                'invoiceId' => $invoiceId,
                'totalPaid' => $totalPaid,
            ]);
        }

        $this->entityManager->flush();

        $this->logger->info('checkout.session.completed processed', [
            'invoiceId' => $invoiceId,
            'paymentId' => $paymentId,
            'paymentStatus' => $payment->getStatus()->value,
            'invoiceStatus' => $invoice->getStatus()->value,
        ]);
    }

    /**
     * Session Checkout expirée (abandon du candidat) → le Payment PENDING passe en FAILED.
     */
    private function handleCheckoutSessionExpired(\Stripe\Event $event): void
    {
        /** @var \Stripe\Checkout\Session $session */
        $session = $event->data->object;
        $paymentId = $session->metadata?->toArray()['paymentId'] ?? null;

        $payment = $paymentId ? $this->entityManager->getRepository(Payment::class)->find($paymentId) : null;

        if (!$payment || $payment->getStatus() !== PaymentStatusEnum::PENDING) {
            return;
        }

        $payment->setStatus(PaymentStatusEnum::FAILED);
        $this->entityManager->flush();

        $this->logger->info('checkout.session.expired processed', ['paymentId' => $paymentId]);
    }

    /**
     * Paiement Stripe échoué → marquer le Payment comme FAILED.
     */
    private function handlePaymentIntentFailed(\Stripe\Event $event): void
    {
        /** @var \Stripe\PaymentIntent $paymentIntent */
        $paymentIntent = $event->data->object;
        $metadata = $paymentIntent->metadata?->toArray() ?? [];

        $paymentId = $metadata['paymentId'] ?? null;

        if (!$paymentId) {
            $this->logger->warning('payment_intent.payment_failed: missing paymentId in metadata');
            return;
        }

        $payment = $this->entityManager->getRepository(Payment::class)->find($paymentId);

        if (!$payment) {
            $this->logger->warning('payment_intent.payment_failed: payment not found', ['paymentId' => $paymentId]);
            return;
        }

        if ($payment->getStatus() !== PaymentStatusEnum::PENDING) {
            $this->logger->info('payment_intent.payment_failed: payment not pending', [
                'paymentId' => $paymentId,
                'status' => $payment->getStatus()->value,
            ]);
            return;
        }

        $payment->setStatus(PaymentStatusEnum::FAILED);
        $this->entityManager->flush();

        $this->logger->info('Payment marked as failed', ['paymentId' => $paymentId]);
    }
}
