<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\TokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class VerifyEmailController extends AbstractController
{
    public function __construct(
        private readonly TokenService $tokenService,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/auth/verify-email/{token}', name: 'auth_verify_email', methods: ['POST'])]
    public function __invoke(string $token): JsonResponse
    {
        $payload = $this->tokenService->validateToken($token);

        if (!$payload || ($payload['type'] ?? null) !== 'verify_email') {
            return new JsonResponse(['message' => 'Token invalide ou expiré.'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findOneByEmail($payload['email']);
        if (!$user) {
            return new JsonResponse(['message' => 'Utilisateur introuvable.'], Response::HTTP_BAD_REQUEST);
        }

        if ($user->isVerified()) {
            return new JsonResponse(['message' => 'Email déjà vérifié.'], Response::HTTP_OK);
        }

        $user->setIsVerified(true);
        $user->setEmailVerifiedAt(new \DateTime());
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Email vérifié avec succès.'], Response::HTTP_OK);
    }
}
