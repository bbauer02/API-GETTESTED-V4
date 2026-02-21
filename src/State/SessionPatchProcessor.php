<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Session;
use App\Enum\SessionValidationEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class SessionPatchProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Session
    {
        /** @var Session $session */
        $session = $data;

        /** @var Session|null $previousData */
        $previousData = $context['previous_data'] ?? null;

        if (!$previousData) {
            throw new UnprocessableEntityHttpException('Impossible de déterminer l\'état précédent de la session.');
        }

        $status = $previousData->getValidation();

        match ($status) {
            SessionValidationEnum::DRAFT => null, // tout est modifiable
            SessionValidationEnum::OPEN => $this->validateOpenChanges($session, $previousData),
            SessionValidationEnum::CLOSE, SessionValidationEnum::CANCELLED => throw new UnprocessableEntityHttpException(
                'Une session en statut ' . $status->value . ' ne peut pas être modifiée.'
            ),
        };

        $this->entityManager->flush();

        return $session;
    }

    private function validateOpenChanges(Session $session, Session $previousData): void
    {
        if ($session->getStart() != $previousData->getStart()) {
            throw new UnprocessableEntityHttpException('La date de début ne peut pas être modifiée en statut OPEN.');
        }

        if ($session->getEnd() != $previousData->getEnd()) {
            throw new UnprocessableEntityHttpException('La date de fin ne peut pas être modifiée en statut OPEN.');
        }

        if ($session->getPlacesAvailable() !== null
            && $previousData->getPlacesAvailable() !== null
            && $session->getPlacesAvailable() < $previousData->getPlacesAvailable()
        ) {
            throw new UnprocessableEntityHttpException('Le nombre de places ne peut qu\'augmenter en statut OPEN.');
        }
    }
}
