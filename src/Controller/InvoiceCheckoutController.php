<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\User;
use App\Enum\InvoiceStatusEnum;
use App\Enum\PaymentMethodEnum;
use App\Enum\PaymentStatusEnum;
use App\Service\InvoiceService;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class InvoiceCheckoutController extends AbstractController
{
    public function __construct(
        private readonly StripeService $stripeService,
        private readonly InvoiceService $invoiceService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/invoices/{id}/checkout', name: 'api_invoice_checkout', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function checkout(Invoice $invoice, Request $request): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Vérifier que l'utilisateur est le propriétaire de l'enrollment lié
        $enrollment = $invoice->getEnrollmentSession();
        if (!$enrollment || !$enrollment->getUser()?->getId()?->equals($currentUser->getId())) {
            throw new AccessDeniedHttpException('Vous ne pouvez payer que vos propres factures.');
        }

        // Vérifier que la facture est émise
        if ($invoice->getStatus() !== InvoiceStatusEnum::ISSUED) {
            throw new UnprocessableEntityHttpException('Seule une facture émise peut être payée.');
        }

        // Vérifier que l'institut a un compte Stripe actif
        $institute = $invoice->getInstitute();
        $stripeAccount = $institute?->getStripeAccount();
        if (!$stripeAccount || !$stripeAccount->isActivated() || !$stripeAccount->isChargesEnabled()) {
            throw new UnprocessableEntityHttpException('L\'institut n\'a pas de compte de paiement actif.');
        }

        $body = json_decode($request->getContent(), true) ?? [];
        $successUrl = $body['successUrl'] ?? null;
        $cancelUrl = $body['cancelUrl'] ?? null;

        if (!$successUrl || !$cancelUrl) {
            throw new BadRequestHttpException('Les URLs successUrl et cancelUrl sont requises.');
        }

        // Créer un Payment PENDING
        $payment = new Payment();
        $payment->setInvoice($invoice);
        $payment->setAmount($invoice->getTotalTTC());
        $payment->setCurrency($invoice->getCurrency());
        $payment->setPaymentMethod(PaymentMethodEnum::STRIPE);
        $payment->setStatus(PaymentStatusEnum::PENDING);
        $payment->setDate(new \DateTime());
        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        // Créer la session Checkout Stripe
        $session = $enrollment->getSession();
        $assessment = $session?->getAssessment();
        $productName = $assessment
            ? $assessment->getLabel() . ' — Session du ' . $session->getStart()?->format('d/m/Y')
            : 'Inscription session #' . $session?->getId();

        $checkoutSession = $this->stripeService->createCheckoutSession(
            stripeAccountId: $stripeAccount->getStripeId(),
            amountInCents: (int) round($invoice->getTotalTTC() * 100),
            totalHTInCents: (int) round($invoice->getTotalHT() * 100),
            currency: $invoice->getCurrency(),
            productName: $productName,
            successUrl: $successUrl,
            cancelUrl: $cancelUrl,
            metadata: [
                'invoiceId' => $invoice->getId()->toRfc4122(),
                'paymentId' => $payment->getId()->toRfc4122(),
                'enrollmentId' => $enrollment->getId()->toRfc4122(),
                'instituteId' => $institute->getId()->toRfc4122(),
            ],
            instituteCommission: $stripeAccount->getCommissionPercent() !== null
                ? (string) $stripeAccount->getCommissionPercent()
                : null,
        );

        // Stocker l'ID du payment intent Stripe
        $payment->setStripePaymentIntentId($checkoutSession->payment_intent ?? $checkoutSession->id);
        $this->entityManager->flush();

        return new JsonResponse([
            'checkoutUrl' => $checkoutSession->url,
            'sessionId' => $checkoutSession->id,
        ], Response::HTTP_OK);
    }

    /**
     * Vérifie le statut du paiement côté Stripe et met à jour la facture.
     * Appelé par le front au retour de Stripe (?payment=success).
     * Compense l'absence de webhook en environnement local.
     */
    #[Route('/api/invoices/{id}/verify-payment', name: 'api_invoice_verify_payment', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function verifyPayment(Invoice $invoice): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Vérifier que l'utilisateur est le propriétaire
        $enrollment = $invoice->getEnrollmentSession();
        if (!$enrollment || !$enrollment->getUser()?->getId()?->equals($currentUser->getId())) {
            throw new AccessDeniedHttpException('Accès refusé.');
        }

        // Si la facture est déjà payée, rien à faire
        if ($invoice->getStatus() === InvoiceStatusEnum::PAID) {
            return new JsonResponse([
                'status' => 'already_paid',
                'invoiceStatus' => $invoice->getStatus()->value,
            ]);
        }

        // Paiements Stripe en attente, du plus récent au plus ancien : le candidat a pu
        // abandonner une première tentative avant de payer avec une seconde.
        $pendingPayments = array_filter(
            $invoice->getPayments()->toArray(),
            fn (Payment $p) => $p->getStatus() === PaymentStatusEnum::PENDING && $p->getStripePaymentIntentId(),
        );
        usort($pendingPayments, fn (Payment $a, Payment $b) => $b->getDate() <=> $a->getDate());

        if (!$pendingPayments) {
            return new JsonResponse([
                'status' => 'no_pending_payment',
                'invoiceStatus' => $invoice->getStatus()->value,
            ]);
        }

        $pendingPayment = null;

        try {
            foreach ($pendingPayments as $candidate) {
                $stripeId = $candidate->getStripePaymentIntentId();

                // L'ID peut être un checkout session ID (cs_*) ou un payment intent ID (pi_*)
                if (str_starts_with($stripeId, 'cs_')) {
                    $checkoutSession = $this->stripeService->retrieveCheckoutSession($stripeId);
                    if ($checkoutSession->status === 'expired') {
                        $candidate->setStatus(PaymentStatusEnum::FAILED);
                        continue;
                    }
                    if ($checkoutSession->payment_status === 'paid') {
                        // Conserver le payment intent : c'est lui qu'il faut fournir pour rembourser
                        if ($checkoutSession->payment_intent) {
                            $candidate->setStripePaymentIntentId($checkoutSession->payment_intent);
                        }
                        $pendingPayment = $candidate;
                        break;
                    }
                } else {
                    $paymentIntent = $this->stripeService->retrievePaymentIntent($stripeId);
                    if ($paymentIntent->status === 'succeeded') {
                        $pendingPayment = $candidate;
                        break;
                    }
                }
            }
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'stripe_error',
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }

        if (!$pendingPayment) {
            $this->entityManager->flush();

            return new JsonResponse([
                'status' => 'not_paid',
                'invoiceStatus' => $invoice->getStatus()->value,
            ]);
        }

        // Transaction unique : paiement + facture commission. Rollback si quoi que ce soit plante.
        $conn = $this->entityManager->getConnection();
        $conn->beginTransaction();

        try {
            // Marquer le paiement comme complété
            $pendingPayment->setStatus(PaymentStatusEnum::COMPLETED);

            // Marquer la facture comme payée
            $invoice->setStatus(InvoiceStatusEnum::PAID);

            // Créer la facture de commission plateforme
            $this->invoiceService->createCommissionInvoice($invoice, $pendingPayment);

            $this->entityManager->flush();
            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }

        return new JsonResponse([
            'status' => 'paid',
            'invoiceStatus' => $invoice->getStatus()->value,
        ]);
    }
}
