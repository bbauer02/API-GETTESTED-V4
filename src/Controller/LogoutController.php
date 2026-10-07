<?php

namespace App\Controller;

use App\Service\SessionRevoker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Déconnexion : le jeton de renouvellement de cet appareil est supprimé côté serveur
 * (un jeton copié avant la déconnexion ne permet plus de rouvrir la session).
 */
class LogoutController extends AbstractController
{
    public function __construct(
        private readonly SessionRevoker $sessionRevoker,
    ) {
    }

    #[Route('/api/auth/logout', name: 'auth_logout', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $refreshToken = $data['refresh_token'] ?? null;

        if (is_string($refreshToken) && $refreshToken !== '') {
            $this->sessionRevoker->revoke($refreshToken);
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
