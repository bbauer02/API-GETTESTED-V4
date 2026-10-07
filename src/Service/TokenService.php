<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Jetons envoyés par email (vérification, réinitialisation, invitation).
 *
 * Les liens de réinitialisation et d'invitation sont à usage unique : ils portent une empreinte
 * du mot de passe et de l'email actuels, qui change dès que le lien a servi (ou que l'email change).
 */
class TokenService
{
    public function __construct(
        private readonly JWTEncoderInterface $jwtEncoder,
        private readonly UserRepository $userRepository,
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
    ) {
    }

    public function generateVerificationToken(User $user): string
    {
        return $this->jwtEncoder->encode([
            'email' => $user->getEmail(),
            'type' => 'verify_email',
            'exp' => time() + 86400, // 24h
        ]);
    }

    public function generateResetToken(User $user): string
    {
        return $this->jwtEncoder->encode([
            'email' => $user->getEmail(),
            'type' => 'reset_password',
            'fp' => $this->accountFingerprint($user),
            'exp' => time() + 3600, // 1h
        ]);
    }

    public function generateInvitationToken(User $user): string
    {
        return $this->jwtEncoder->encode([
            'email' => $user->getEmail(),
            'type' => 'invitation',
            'fp' => $this->accountFingerprint($user),
            'exp' => time() + 7 * 86400, // 7 jours
        ]);
    }

    public function validateToken(string $token): ?array
    {
        try {
            return $this->jwtEncoder->decode($token);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Utilisateur d'un lien à usage unique, ou null si le lien est invalide, expiré ou déjà utilisé.
     */
    public function userForSingleUseToken(string $token, string $type): ?User
    {
        $payload = $this->validateToken($token);
        if (!$payload || ($payload['type'] ?? null) !== $type || !isset($payload['email'], $payload['fp'])) {
            return null;
        }

        $user = $this->userRepository->findOneByEmail($payload['email']);
        if (!$user || !hash_equals($this->accountFingerprint($user), (string) $payload['fp'])) {
            return null;
        }

        return $user;
    }

    /**
     * Version du mot de passe portée par les jetons de connexion : changer de mot de passe
     * invalide immédiatement les sessions ouvertes ailleurs.
     */
    public function passwordVersion(User $user): string
    {
        return substr(hash_hmac('sha256', 'pwd|' . $user->getPassword(), $this->secret), 0, 16);
    }

    private function accountFingerprint(User $user): string
    {
        return substr(hash_hmac('sha256', 'account|' . $user->getPassword() . '|' . mb_strtolower((string) $user->getEmail()), $this->secret), 0, 24);
    }
}
