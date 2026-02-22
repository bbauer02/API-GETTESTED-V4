<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Session;
use Doctrine\ORM\EntityManagerInterface;
use App\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Workflow\WorkflowInterface;

class SessionTransitionProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly WorkflowInterface $sessionLifecycleStateMachine,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Session
    {
        /** @var Session $session */
        $session = $data;

        $transition = $session->getTransition();
        if (!$transition) {
            throw new UnprocessableEntityHttpException('Le champ "transition" est requis.');
        }

        if (!$this->sessionLifecycleStateMachine->can($session, $transition)) {
            throw new ConflictHttpException(
                sprintf('La transition "%s" n\'est pas possible depuis le statut "%s".', $transition, $session->getValidation()->value)
            );
        }

        $this->sessionLifecycleStateMachine->apply($session, $transition);

        $this->entityManager->flush();

        return $session;
    }
}
