<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\SessionRevoker;
use App\Service\TokenService;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

class ResetPasswordController extends AbstractController
{
    public function __construct(
        private readonly TokenService $tokenService,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly string $frontendUrl,
        private readonly RateLimiterFactoryInterface $forgotPasswordLimiter,
        private readonly SessionRevoker $sessionRevoker,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/api/auth/forgot-password', name: 'auth_forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            return $this->hydraSuccess('Demande traitée.');
        }

        // Limite par adresse IP et par email : pas de bombardement de mails
        $ipLimit = $this->forgotPasswordLimiter->create('ip-' . $request->getClientIp())->consume();
        $emailLimit = $this->forgotPasswordLimiter->create('email-' . mb_strtolower((string) $email))->consume();
        if (!$ipLimit->isAccepted() || !$emailLimit->isAccepted()) {
            return $this->hydraError(Response::HTTP_TOO_MANY_REQUESTS, 'Trop de demandes. Réessayez dans quelques minutes.');
        }

        $user = $this->userRepository->findOneByEmail($email);

        // L'email part après l'envoi de la réponse (kernel.terminate) : le temps de réponse
        // ne dépend plus de l'existence du compte (pas d'énumération des comptes).
        if ($user) {
            $this->sendResetLinkAfterResponse($user);
        }

        // Always return 200 to not reveal email existence
        return $this->hydraSuccess('Si un compte existe avec cette adresse email, un lien de réinitialisation a été envoyé.');
    }

    #[Route('/api/auth/reset-password/{token}', name: 'auth_reset_password', methods: ['POST'])]
    public function resetPassword(string $token, Request $request): JsonResponse
    {
        // Lien à usage unique : invalide dès que le mot de passe a changé
        $user = $this->tokenService->userForSingleUseToken($token, 'reset_password');
        if (!$user) {
            return $this->hydraError(Response::HTTP_BAD_REQUEST, 'Ce lien est invalide, expiré ou a déjà été utilisé.');
        }

        $data = json_decode($request->getContent(), true);
        $newPassword = $data['newPassword'] ?? null;

        if (!$newPassword || strlen($newPassword) < 8) {
            return $this->hydraError(Response::HTTP_UNPROCESSABLE_ENTITY, 'Le mot de passe doit contenir au moins 8 caractères.');
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);
        $this->entityManager->flush();

        // Toutes les sessions ouvertes sont fermées (compte potentiellement compromis)
        $this->sessionRevoker->revokeAll($user);

        return $this->hydraSuccess('Mot de passe réinitialisé avec succès.');
    }

    private function sendResetLinkAfterResponse(User $user): void
    {
        // Le message est préparé pendant la requête (Twig a besoin de la mise en mémoire tampon
        // de la sortie, indisponible une fois la réponse envoyée en mode worker) ; seul l'envoi
        // SMTP, la partie lente, est différé.
        $token = $this->tokenService->generateResetToken($user);
        $html = $this->twig->render('email/reset_password.html.twig', [
            'user' => $user,
            'resetUrl' => rtrim($this->frontendUrl, '/') . '/auth/jwt/reset-password/?token=' . urlencode($token),
        ]);
        $email = (new Email())
            ->to($user->getEmail())
            ->subject('Réinitialisation de votre mot de passe - GETTESTED')
            ->html($html);

        $listener = function () use (&$listener, $email): void {
            // Le répartiteur survit aux requêtes en mode worker : écouteur à usage unique
            $this->eventDispatcher->removeListener(KernelEvents::TERMINATE, $listener);

            try {
                $this->mailer->send($email);
            } catch (\Throwable $e) {
                $this->logger->error('Envoi du lien de réinitialisation échoué : ' . $e->getMessage());
            }
        };

        $this->eventDispatcher->addListener(KernelEvents::TERMINATE, $listener);
    }

    private function hydraSuccess(string $message): JsonResponse
    {
        return new JsonResponse([
            '@context' => '/api/contexts/ResetPassword',
            '@type' => 'ResetPassword',
            'message' => $message,
        ], Response::HTTP_OK, ['Content-Type' => 'application/ld+json']);
    }

    private function hydraError(int $status, string $detail): JsonResponse
    {
        return new JsonResponse([
            '@context' => '/api/contexts/Error',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => $detail,
            'status' => $status,
        ], $status, ['Content-Type' => 'application/ld+json']);
    }
}
