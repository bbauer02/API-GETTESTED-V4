<?php

namespace App\Service;

use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Fermeture des sessions d'un compte (changement ou réinitialisation du mot de passe, déconnexion).
 * Les jetons d'accès déjà émis deviennent invalides via la version du mot de passe (JwtPasswordVersionSubscriber).
 */
class SessionRevoker
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly RefreshTokenGeneratorInterface $refreshTokenGenerator,
        private readonly RefreshTokenManagerInterface $refreshTokenManager,
        #[Autowire('%gesdinet_jwt_refresh_token.ttl%')]
        private readonly int $refreshTokenTtl,
    ) {
    }

    /** Supprime tous les jetons de renouvellement du compte (toutes les sessions). */
    public function revokeAll(User $user): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(RefreshToken::class, 'rt')
            ->where('rt.username = :username')
            ->setParameter('username', $user->getUserIdentifier())
            ->getQuery()
            ->execute();
    }

    /** Supprime un jeton de renouvellement (déconnexion de cet appareil). */
    public function revoke(string $refreshToken): void
    {
        $token = $this->refreshTokenManager->get($refreshToken);
        if ($token) {
            $this->refreshTokenManager->delete($token);
        }
    }

    /**
     * Nouveaux jetons pour la session courante, après fermeture des autres.
     *
     * @return array{access_token: string, refresh_token: string}
     */
    public function issueTokens(User $user): array
    {
        $refreshToken = $this->refreshTokenGenerator->createForUserWithTtl($user, $this->refreshTokenTtl);
        $this->refreshTokenManager->save($refreshToken);

        return [
            'access_token' => $this->jwtManager->create($user),
            'refresh_token' => $refreshToken->getRefreshToken(),
        ];
    }
}
