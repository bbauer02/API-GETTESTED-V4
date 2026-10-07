<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Session;
use App\Service\RefundService;
use Doctrine\ORM\EntityManagerInterface;
use App\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Workflow\WorkflowInterface;

class SessionTransitionProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly WorkflowInterface $sessionLifecycleStateMachine,
        private readonly RefundService $refundService,
    ) {
    }

    private const CANCEL_WITH_REFUND = ['cancel_from_open', 'cancel_from_locked'];

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Session
    {
        /** @var Session $session */
        $session = $data;

        $transition = $session->getTransition();
        if (!$transition) {
            throw new UnprocessableEntityHttpException('Le champ "transition" est requis.');
        }

        // La date limite n'est modifiable ici que pour la réouverture d'une session verrouillée
        $originalLimitDate = $this->entityManager->getUnitOfWork()->getOriginalEntityData($session)['limitDateSubscribe'] ?? null;
        if ($transition === 'reopen_from_locked') {
            $newLimitDate = $session->getLimitDateSubscribe();
            if (!$newLimitDate || $newLimitDate == $originalLimitDate || $newLimitDate <= new \DateTime()) {
                throw new UnprocessableEntityHttpException(
                    'Pour rouvrir les inscriptions, indiquez une nouvelle date limite d\'inscription dans le futur.'
                );
            }
            if ($session->getStart() && $newLimitDate > $session->getStart()) {
                throw new UnprocessableEntityHttpException(
                    'La date limite d\'inscription doit précéder le début de la session.'
                );
            }
        } else {
            $session->setLimitDateSubscribe($originalLimitDate);
        }

        if (!$this->sessionLifecycleStateMachine->can($session, $transition)) {
            // Remonter la raison du blocage (garde du workflow) plutôt qu'un message générique
            $reasons = [];
            foreach ($this->sessionLifecycleStateMachine->buildTransitionBlockerList($session, $transition) as $blocker) {
                if ($blocker->getCode() === \Symfony\Component\Workflow\TransitionBlocker::BLOCKED_BY_EXPRESSION_GUARD_LISTENER
                    || $blocker->getCode() === \Symfony\Component\Workflow\TransitionBlocker::UNKNOWN
                ) {
                    $reasons[] = $blocker->getMessage();
                }
            }

            throw new ConflictHttpException($reasons
                ? implode(' ', $reasons)
                : sprintf('La transition "%s" n\'est pas possible depuis le statut "%s".', $transition, $session->getStatus()->value)
            );
        }

        if ($transition === 'reopen_from_locked') {
            $session->setLockedAutomaticallyAt(null);
        }

        $refundErrors = [];
        if (in_array($transition, self::CANCEL_WITH_REFUND, true)) {
            // Rembourser chaque inscription (Stripe + avoir + factures annulées) avant l'annulation.
            // Une erreur Stripe est loggée et remontée dans refundErrors, mais ne bloque pas la transition.
            foreach ($session->getEnrollments() as $enrollment) {
                $result = $this->refundService->refundEnrollment($enrollment, 'requested_by_customer');
                foreach ($result['errors'] as $error) {
                    $refundErrors[] = $error;
                }
            }
        }

        $this->sessionLifecycleStateMachine->apply($session, $transition);

        $this->entityManager->flush();

        $session->setRefundErrors($refundErrors);

        return $session;
    }
}
