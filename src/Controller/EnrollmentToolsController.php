<?php

namespace App\Controller;

use App\Entity\EnrollmentSession;
use App\Security\Voter\EnrollmentVoter;
use App\Service\RefundService;
use App\Service\SmsService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Outils sur une inscription : aperçu de remboursement et envoi de SMS au candidat.
 */
class EnrollmentToolsController extends AbstractController
{
    use HydraResponseTrait;

    public const SMS_MAX_LENGTH = 480;

    public function __construct(
        private readonly RefundService $refundService,
        private readonly SmsService $smsService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * GET /api/enrollment-sessions/{id}/refund-preview
     */
    #[Route('/api/enrollment-sessions/{id}/refund-preview', name: 'api_enrollment_refund_preview', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function refundPreview(EnrollmentSession $enrollment): JsonResponse
    {
        if (!$this->isGranted(EnrollmentVoter::ENROLLMENT_CANCEL, $enrollment)) {
            return $this->hydraError(Response::HTTP_FORBIDDEN, "Vous n'avez pas les droits pour consulter cette inscription.");
        }

        $preview = $this->refundService->previewEnrollment($enrollment);

        return $this->hydraJson('EnrollmentRefundPreview', ['enrollmentId' => (string) $enrollment->getId()] + $preview);
    }

    /**
     * POST /api/enrollment-sessions/{id}/send-sms  body { message }
     */
    #[Route('/api/enrollment-sessions/{id}/send-sms', name: 'api_enrollment_send_sms', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function sendSms(EnrollmentSession $enrollment, Request $request): JsonResponse
    {
        if (!$this->isGranted(EnrollmentVoter::ENROLLMENT_EDIT, $enrollment)) {
            return $this->hydraError(Response::HTTP_FORBIDDEN, "Vous n'avez pas les droits pour contacter ce candidat.");
        }

        $body = json_decode($request->getContent(), true) ?? [];
        $message = trim((string) ($body['message'] ?? ''));

        if ($message === '') {
            return $this->hydraError(Response::HTTP_UNPROCESSABLE_ENTITY, 'Le message est requis.');
        }
        if (mb_strlen($message) > self::SMS_MAX_LENGTH) {
            return $this->hydraError(Response::HTTP_UNPROCESSABLE_ENTITY, sprintf('Le message ne doit pas dépasser %d caractères.', self::SMS_MAX_LENGTH));
        }

        $candidate = $enrollment->getUser();
        $phone = SmsService::formatPhone($candidate?->getPhoneCountryCode(), $candidate?->getPhone());
        if (!$phone) {
            return $this->hydraError(Response::HTTP_UNPROCESSABLE_ENTITY, "Le candidat n'a pas de numéro de téléphone.");
        }

        try {
            $this->smsService->send($phone, $message);
        } catch (\Throwable $e) {
            $this->logger->error('Envoi SMS échoué', ['enrollmentId' => (string) $enrollment->getId(), 'error' => $e->getMessage()]);

            return $this->hydraError(Response::HTTP_BAD_GATEWAY, "L'envoi du SMS a échoué : " . $e->getMessage());
        }

        return $this->hydraJson('SmsSent', [
            'sent' => true,
            'provider' => $this->smsService->getProvider(),
            'to' => $phone,
        ]);
    }
}
