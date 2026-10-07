<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\SmsService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Vérification du numéro de téléphone de l'utilisateur connecté par code SMS.
 */
class PhoneVerificationController extends AbstractController
{
    use HydraResponseTrait;

    private const CODE_TTL_MINUTES = 10;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SmsService $smsService,
        private readonly LoggerInterface $logger,
        private readonly string $appEnv,
        private readonly RateLimiterFactoryInterface $phoneSendCodeLimiter,
        private readonly RateLimiterFactoryInterface $phoneVerifyCodeLimiter,
    ) {
    }

    /**
     * POST /api/users/me/phone/send-code
     */
    #[Route('/api/users/me/phone/send-code', name: 'api_user_phone_send_code', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function sendCode(): JsonResponse
    {
        /** @var User $current */
        $current = $this->getUser();
        $user = $this->entityManager->getRepository(User::class)->find($current->getId());

        // SMS payants : 3 envois maximum par quart d'heure et par utilisateur
        if (!$this->phoneSendCodeLimiter->create((string) $user->getId())->consume()->isAccepted()) {
            return $this->hydraError(Response::HTTP_TOO_MANY_REQUESTS, 'Trop de demandes de code. Réessayez dans quelques minutes.');
        }

        $phone = SmsService::formatPhone($user->getPhoneCountryCode(), $user->getPhone());
        if (!$phone) {
            return $this->hydraError(Response::HTTP_UNPROCESSABLE_ENTITY, 'Aucun numéro de téléphone renseigné sur votre profil.');
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = (new \DateTime())->modify(sprintf('+%d minutes', self::CODE_TTL_MINUTES));

        $user->setPhoneVerificationCode(password_hash($code, PASSWORD_BCRYPT));
        $user->setPhoneVerificationExpiresAt($expiresAt);
        $this->entityManager->flush();

        $message = sprintf('GETTESTED : votre code de vérification est %s (valable %d minutes).', $code, self::CODE_TTL_MINUTES);

        // Le code n'est jamais journalisé
        $this->logger->info('Code de vérification téléphone généré', [
            'userId' => (string) $user->getId(),
            'to' => $phone,
        ]);

        try {
            $this->smsService->send($phone, $message);
        } catch (\Throwable $e) {
            $this->logger->error('Envoi du code SMS échoué', ['userId' => (string) $user->getId(), 'error' => $e->getMessage()]);

            return $this->hydraError(Response::HTTP_BAD_GATEWAY, "L'envoi du SMS a échoué : " . $e->getMessage());
        }

        $data = [
            'sent' => true,
            'provider' => $this->smsService->getProvider(),
            'to' => $phone,
            'expiresAt' => $expiresAt->format(\DateTimeInterface::ATOM),
        ];
        if ($this->appEnv === 'dev') {
            $data['debugCode'] = $code;
        }

        return $this->hydraJson('PhoneVerificationCode', $data);
    }

    /**
     * POST /api/users/me/phone/verify-code  body { code }
     */
    #[Route('/api/users/me/phone/verify-code', name: 'api_user_phone_verify_code', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function verifyCode(Request $request): JsonResponse
    {
        /** @var User $current */
        $current = $this->getUser();
        $user = $this->entityManager->getRepository(User::class)->find($current->getId());

        $body = json_decode($request->getContent(), true) ?? [];
        $code = trim((string) ($body['code'] ?? ''));
        if (!preg_match('/^\d{6}$/', $code)) {
            return $this->hydraError(Response::HTTP_UNPROCESSABLE_ENTITY, 'Le code doit contenir 6 chiffres.');
        }

        // 5 essais maximum : au-delà, le code est invalidé et il faut en demander un nouveau
        if (!$this->phoneVerifyCodeLimiter->create((string) $user->getId())->consume()->isAccepted()) {
            $user->setPhoneVerificationCode(null);
            $user->setPhoneVerificationExpiresAt(null);
            $this->entityManager->flush();

            return $this->hydraError(Response::HTTP_TOO_MANY_REQUESTS, 'Trop d\'essais. Demandez un nouveau code dans quelques minutes.');
        }

        $hash = $user->getPhoneVerificationCode();
        $expiresAt = $user->getPhoneVerificationExpiresAt();
        if (!$hash || !$expiresAt) {
            return $this->hydraError(Response::HTTP_BAD_REQUEST, 'Aucun code en attente. Demandez un nouveau code.');
        }
        if ($expiresAt < new \DateTime()) {
            return $this->hydraError(Response::HTTP_BAD_REQUEST, 'Le code a expiré. Demandez un nouveau code.');
        }
        if (!password_verify($code, $hash)) {
            return $this->hydraError(Response::HTTP_BAD_REQUEST, 'Code invalide.');
        }

        $user->setPhoneVerifiedAt(new \DateTime());
        $user->setPhoneVerificationCode(null);
        $user->setPhoneVerificationExpiresAt(null);
        $this->entityManager->flush();

        return $this->hydraJson('PhoneVerification', [
            'verified' => true,
            'phoneVerifiedAt' => $user->getPhoneVerifiedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }
}
