<?php

namespace App\Controller;

use App\Entity\Session;
use App\Enum\InvoiceStatusEnum;
use App\Enum\PaymentStatusEnum;
use App\Security\Voter\SessionVoter;
use App\Service\RefundService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class SessionCancelPreviewController extends AbstractController
{
    use HydraResponseTrait;

    public function __construct(
        private readonly RefundService $refundService,
    ) {
    }

    /**
     * GET /api/sessions/{id}/cancel-preview
     * Aperçu de l'impact d'une annulation : inscriptions, remboursements, factures.
     */
    #[Route('/api/sessions/{id}/cancel-preview', name: 'api_session_cancel_preview', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(Session $session): JsonResponse
    {
        if (!$this->isGranted(SessionVoter::SESSION_TRANSITION, $session)) {
            return $this->hydraError(Response::HTTP_FORBIDDEN, "Vous n'avez pas les droits pour annuler cette session.");
        }

        $enrollmentCount = 0;
        $paidEnrollmentCount = 0;
        $refundTotal = 0.0;
        $invoicesToCancel = 0;
        $currency = 'EUR';

        foreach ($session->getEnrollments() as $enrollment) {
            $enrollmentCount++;
            $paid = false;

            foreach ($this->refundService->enrollmentInvoices($enrollment) as $invoice) {
                $currency = $invoice->getCurrency() ?: $currency;
                if (in_array($invoice->getStatus(), [InvoiceStatusEnum::DRAFT, InvoiceStatusEnum::ISSUED, InvoiceStatusEnum::PAID], true)) {
                    $invoicesToCancel++;
                }
                foreach ($invoice->getPayments() as $payment) {
                    if ($payment->getStatus() === PaymentStatusEnum::COMPLETED) {
                        $paid = true;
                        $refundTotal += (float) $payment->getAmount();
                    }
                }
            }

            if ($paid) {
                $paidEnrollmentCount++;
            }
        }

        return $this->hydraJson('SessionCancelPreview', [
            'sessionId' => (string) $session->getId(),
            'status' => $session->getStatus()->value,
            'enrollmentCount' => $enrollmentCount,
            'paidEnrollmentCount' => $paidEnrollmentCount,
            'refundTotal' => round($refundTotal, 2),
            'currency' => $currency,
            'invoicesToCancel' => $invoicesToCancel,
        ]);
    }
}
