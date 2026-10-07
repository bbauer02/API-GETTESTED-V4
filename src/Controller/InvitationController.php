<?php

namespace App\Controller;

use App\Enum\MembershipStatusEnum;
use App\Repository\UserRepository;
use App\Service\TokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoints publics d'acceptation d'invitation (lien reçu par email).
 */
class InvitationController extends AbstractController
{
    public function __construct(
        private readonly TokenService $tokenService,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('/api/auth/invitation/{token}', name: 'auth_invitation_get', methods: ['GET'])]
    public function show(string $token): JsonResponse
    {
        $payload = $this->tokenService->validateToken($token);
        if (!$payload || ($payload['type'] ?? null) !== 'invitation') {
            return $this->hydraError(Response::HTTP_BAD_REQUEST, 'Token invalide ou expiré.');
        }

        $user = $this->userRepository->findOneByEmail($payload['email']);
        if (!$user) {
            return $this->hydraError(Response::HTTP_BAD_REQUEST, 'Token invalide.');
        }

        $instituteLabel = null;
        foreach ($user->getMemberships() as $membership) {
            if ($membership->getStatus() === MembershipStatusEnum::PENDING) {
                $instituteLabel = $membership->getInstitute()?->getLabel();
                break;
            }
        }
        if ($instituteLabel === null) {
            $first = $user->getMemberships()->first();
            $instituteLabel = $first ? $first->getInstitute()?->getLabel() : null;
        }

        return new JsonResponse([
            '@context' => '/api/contexts/Invitation',
            '@type' => 'Invitation',
            'email' => $user->getEmail(),
            'firstname' => $user->getFirstname(),
            'lastname' => $user->getLastname(),
            'institute' => $instituteLabel,
            'alreadyAccepted' => $user->isActive(),
        ], Response::HTTP_OK, ['Content-Type' => 'application/ld+json']);
    }

    #[Route('/api/auth/accept-invitation/{token}', name: 'auth_invitation_accept', methods: ['POST'])]
    public function accept(string $token, Request $request): JsonResponse
    {
        $payload = $this->tokenService->validateToken($token);
        if (!$payload || ($payload['type'] ?? null) !== 'invitation') {
            return $this->hydraError(Response::HTTP_BAD_REQUEST, 'Token invalide ou expiré.');
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $password = $data['password'] ?? null;
        if (!is_string($password) || strlen($password) < 8) {
            return $this->hydraError(Response::HTTP_UNPROCESSABLE_ENTITY, 'Le mot de passe doit contenir au moins 8 caractères.');
        }

        $user = $this->userRepository->findOneByEmail($payload['email']);
        if (!$user) {
            return $this->hydraError(Response::HTTP_BAD_REQUEST, 'Token invalide.');
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setIsActive(true);
        $user->setIsVerified(true);
        $user->setEmailVerifiedAt(new \DateTime());

        foreach ($user->getMemberships() as $membership) {
            if ($membership->getStatus() === MembershipStatusEnum::PENDING) {
                $membership->setStatus(MembershipStatusEnum::ACTIVE);
            }
        }

        $this->entityManager->flush();

        return new JsonResponse([
            '@context' => '/api/contexts/Invitation',
            '@type' => 'Invitation',
            'message' => 'Invitation acceptée. Vous pouvez maintenant vous connecter.',
            'email' => $user->getEmail(),
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
