<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Session;
use App\Enum\SessionStatusEnum;
use App\Security\Voter\SessionVoter;
use App\Service\SessionAutoLockService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * GET /api/sessions/{id} : applique le verrouillage automatique paresseux
 * (OPEN + date limite dépassée → LOCKED) avant de renvoyer la session.
 * Une session en brouillon n'est visible que par les gestionnaires de son institut.
 */
class SessionItemProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.item_provider')]
        private readonly ProviderInterface $itemProvider,
        private readonly SessionAutoLockService $autoLockService,
        private readonly Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $session = $this->itemProvider->provide($operation, $uriVariables, $context);

        if ($session instanceof Session) {
            if ($session->getStatus() === SessionStatusEnum::DRAFT
                && !$this->security->isGranted(SessionVoter::SESSION_VIEW_ALL, $session)
            ) {
                throw new NotFoundHttpException('Session introuvable.');
            }

            $this->autoLockService->lockIfExpired($session);
        }

        return $session;
    }
}
