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
    ) {
    }

    #[Route('/api/auth/forgot-password', name: 'auth_forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            return new JsonResponse(['message' => 'Demande traitée.'], Response::HTTP_OK);
        }

        $user = $this->userRepository->findOneByEmail($email);

        if ($user) {
            $token = $this->tokenService->generateResetToken($user);

            $html = $this->twig->render('email/reset_password.html.twig', [
                'user' => $user,
                'token' => $token,
            ]);

            $emailMessage = (new Email())
                ->to($user->getEmail())
                ->subject('Réinitialisation de votre mot de passe - GETTESTED')
                ->html($html);

            $this->mailer->send($emailMessage);
        }

        // Always return 200 to not reveal email existence
        return new JsonResponse(['message' => 'Si un compte existe avec cette adresse email, un lien de réinitialisation a été envoyé.'], Response::HTTP_OK);
    }

    #[Route('/api/auth/reset-password/{token}', name: 'auth_reset_password', methods: ['POST'])]
    public function resetPassword(string $token, Request $request): JsonResponse
    {
        $payload = $this->tokenService->validateToken($token);

        if (!$payload || ($payload['type'] ?? null) !== 'reset_password') {
            return new JsonResponse(['message' => 'Token invalide ou expiré.'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        $newPassword = $data['newPassword'] ?? null;

        if (!$newPassword || strlen($newPassword) < 8) {
            return new JsonResponse(['message' => 'Le mot de passe doit contenir au moins 8 caractères.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $this->userRepository->findOneByEmail($payload['email']);
        if (!$user) {
            return new JsonResponse(['message' => 'Token invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Mot de passe réinitialisé avec succès.'], Response::HTTP_OK);
    }
}
