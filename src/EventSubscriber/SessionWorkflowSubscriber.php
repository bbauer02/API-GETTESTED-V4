<?php

namespace App\EventSubscriber;

use App\Entity\Session;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\GuardEvent;

class SessionWorkflowSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.session_lifecycle.guard.open' => 'guardOpen',
        ];
    }

    public function guardOpen(GuardEvent $event): void
    {
        /** @var Session $session */
        $session = $event->getSubject();

        $institute = $session->getInstitute();
        if (!$institute) {
            $event->setBlocked(true, 'La session doit être associée à un institut.');
            return;
        }

        $stripeAccount = $institute->getStripeAccount();
        if (!$stripeAccount || !$stripeAccount->isActivated()) {
            $event->setBlocked(true, 'Le compte Stripe de l\'institut doit être activé.');
            return;
        }

        $assessment = $session->getAssessment();
        if (!$assessment || $assessment->getSkills()->isEmpty()) {
            $event->setBlocked(true, 'L\'assessment doit avoir au moins un skill.');
            return;
        }

        if ($session->getScheduledExams()->isEmpty()) {
            $event->setBlocked(true, 'La session doit avoir au moins un examen planifié.');
            return;
        }
    }
}
