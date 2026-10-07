<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réponses JSON-LD (Hydra) pour les contrôleurs hors API Platform.
 */
trait HydraResponseTrait
{
    private function hydraJson(string $type, array $data, int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse(
            ['@context' => '/api/contexts/' . $type, '@type' => $type] + $data,
            $status,
            ['Content-Type' => 'application/ld+json']
        );
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
