<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\SessionDocumentPublicationRepository;
use App\Repository\SessionRepository;
use App\Service\DocumentAccessService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SessionDocumentPublicationProvider implements ProviderInterface
{
    public function __construct(
        private readonly SessionRepository $sessionRepository,
        private readonly SessionDocumentPublicationRepository $publicationRepository,
        private readonly DocumentAccessService $documentAccessService,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $sessionId = $uriVariables['sessionId'] ?? null;
        if (!$sessionId) {
            throw new NotFoundHttpException('Session not found.');
        }

        $session = $this->sessionRepository->find($sessionId);
        if (!$session) {
            throw new NotFoundHttpException('Session not found.');
        }

        $publications = $this->publicationRepository->findBy(['session' => $session], ['publishedAt' => 'DESC']);

        // Disponibilité calculée pour la session (sans inscription)
        foreach ($publications as $publication) {
            if ($publication->getDocumentType()) {
                $publication->setAvailability(
                    $this->documentAccessService->availability($publication->getDocumentType(), $session)
                );
            }
        }

        return $publications;
    }
}
