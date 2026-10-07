<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\TokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

        $user = $this->userRepository->findOneByEmail($email);

        if ($user) {
            $token = $this->tokenService->generateResetToken($user);

            $html = $this->twig->render('email/reset_password.html.twig', [
                'user' => $user,
                'resetUrl' => rtrim($this->frontendUrl, '/') . '/auth/jwt/reset-password/?token=' . urlencode($token),
            ]);

            $emailMessage = (new Email())
                ->to($user->getEmail())
                ->subject('Réinitialisation de votre mot de passe - GETTESTED')
                ->html($html);

            $this->mailer->send($emailMessage);
        }

        // Always return 200 to not reveal email existence
        return $this->hydraSuccess('Si un compte existe avec cette adresse email, un lien de réinitialisation a été envoyé.');
    }

    #[Route('/api/auth/reset-password/{token}', name: 'auth_reset_password', methods: ['POST'])]
    public function resetPassword(string $token, Request $request): JsonResponse
    {
        $payload = $this->tokenService->validateToken($token);

        if (!$payload || ($payload['type'] ?? null) !== 'reset_password') {
            return $this->hydraError(Response::HTTP_BAD_REQUEST, 'Token invalide ou expiré.');
        }

        $data = json_decode($request->getContent(), true);
        $newPassword = $data['newPassword'] ?? null;

        if (!$newPassword || strlen($newPassword) < 8) {
            return $this->hydraError(Response::HTTP_UNPROCESSABLE_ENTITY, 'Le mot de passe doit contenir au moins 8 caractères.');
        }

        $user = $this->userRepository->findOneByEmail($payload['email']);
        if (!$user) {
            return $this->hydraError(Response::HTTP_BAD_REQUEST, 'Token invalide.');
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);
        $this->entityManager->flush();

        return $this->hydraSuccess('Mot de passe réinitialisé avec succès.');
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
