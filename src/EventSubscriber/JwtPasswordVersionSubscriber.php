<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\TokenService;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Les jetons d'accès portent la version du mot de passe : après un changement de mot de passe,
 * les jetons émis avant sont refusés (sessions volées ou ouvertes sur d'autres appareils).
 */
class JwtPasswordVersionSubscriber implements EventSubscriberInterface
{
    public const CLAIM = 'pv';

    public function __construct(
        private readonly TokenService $tokenService,
        private readonly UserRepository $userRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::JWT_CREATED => 'onJwtCreated',
            Events::JWT_DECODED => 'onJwtDecoded',
        ];
    }

    public function onJwtCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $payload = $event->getData();
        $payload[self::CLAIM] = $this->tokenService->passwordVersion($user);
        $event->setData($payload);
    }

    public function onJwtDecoded(JWTDecodedEvent $event): void
    {
        $payload = $event->getPayload();
        // Jeton émis avant cette règle : refusé, le front le renouvelle avec son refresh token
        if (!isset($payload[self::CLAIM])) {
            $event->markAsInvalid();

            return;
        }

        $user = $this->userRepository->findOneByEmail((string) ($payload['username'] ?? ''));
        if (!$user || !hash_equals($this->tokenService->passwordVersion($user), (string) $payload[self::CLAIM])) {
            $event->markAsInvalid();
        }
    }
}
