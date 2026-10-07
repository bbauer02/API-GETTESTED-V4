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
            return $this->hydraError(Response::HTTP_BAD_REQUEST, 'Token invalide ou expiré.');
        }

        $user = $this->userRepository->findOneByEmail($payload['email']);
        if (!$user) {
            return $this->hydraError(Response::HTTP_BAD_REQUEST, 'Utilisateur introuvable.');
        }

        if ($user->isVerified()) {
            return $this->hydraSuccess('Email déjà vérifié.');
        }

        $user->setIsVerified(true);
        $user->setEmailVerifiedAt(new \DateTime());
        $this->entityManager->flush();

        return $this->hydraSuccess('Email vérifié avec succès.');
    }

    private function hydraSuccess(string $message): JsonResponse
    {
        return new JsonResponse([
            '@context' => '/api/contexts/VerifyEmail',
            '@type' => 'VerifyEmail',
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
