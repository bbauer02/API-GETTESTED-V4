<?php

namespace App\Controller;

use App\Service\SessionRevoker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ChangePasswordController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly SessionRevoker $sessionRevoker,
    ) {
    }

    #[Route('/api/me/change-password', name: 'api_change_password', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $currentPassword = $data['currentPassword'] ?? null;
        $newPassword = $data['newPassword'] ?? null;

        if (!$currentPassword || !$newPassword) {
            return $this->hydraError(
                Response::HTTP_BAD_REQUEST,
                'Les champs currentPassword et newPassword sont requis.',
            );
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            return $this->hydraError(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Le mot de passe actuel est incorrect.',
            );
        }

        if (strlen($newPassword) < 8) {
            return $this->hydraError(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Le nouveau mot de passe doit contenir au moins 8 caractères.',
            );
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $this->entityManager->flush();

        // Les sessions ouvertes ailleurs sont fermées ; celle-ci reçoit de nouveaux jetons
        $this->sessionRevoker->revokeAll($user);
        $tokens = $this->sessionRevoker->issueTokens($user);

        return new JsonResponse([
            '@context' => '/api/contexts/ChangePassword',
            '@type' => 'ChangePassword',
            'message' => 'Mot de passe modifié avec succès.',
            ...$tokens,
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
