<?php

namespace App\EventSubscriber;

use App\Entity\Session;
use App\Entity\SessionDocumentPublication;
use App\Repository\DocumentTypeRepository;
use App\Repository\SessionDocumentPublicationRepository;
use App\Service\DocumentAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Event\GuardEvent;

class SessionWorkflowSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DocumentTypeRepository $documentTypeRepository,
        private readonly SessionDocumentPublicationRepository $publicationRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.session_lifecycle.guard.open' => 'guardOpen',
            'workflow.session_lifecycle.guard.validate' => 'guardValidate',
            'workflow.session_lifecycle.guard.reopen' => 'guardReopen',
            'workflow.session_lifecycle.completed.open' => 'onOpen',
            'workflow.session_lifecycle.completed.validate' => 'onValidate',
            'workflow.session_lifecycle.completed.cancel_from_draft' => 'onCancel',
            'workflow.session_lifecycle.completed.cancel_from_open' => 'onCancel',
            'workflow.session_lifecycle.completed.cancel_from_locked' => 'onCancel',
        ];
    }

    /**
     * LOCKED → VALIDATED : la session est validée une fois tenue, pas avant sa dernière épreuve.
     */
    public function guardValidate(GuardEvent $event): void
    {
        /** @var Session $session */
        $session = $event->getSubject();

        $lastStart = $session->getStart();
        foreach ($session->getScheduledExams() as $scheduledExam) {
            $start = $scheduledExam->getStartDate();
            if ($start && ($lastStart === null || $start > $lastStart)) {
                $lastStart = $start;
            }
        }

        if ($lastStart !== null && $lastStart > new \DateTime()) {
            $event->setBlocked(true, sprintf(
                'La session ne peut être validée qu\'après sa dernière épreuve (le %s).',
                $lastStart->format('d/m/Y à H:i')
            ));
        }
    }

    /**
     * CANCELLED → DRAFT : impossible si des candidats étaient inscrits (ils ont été remboursés).
     */
    public function guardReopen(GuardEvent $event): void
    {
        /** @var Session $session */
        $session = $event->getSubject();

        if (!$session->getEnrollments()->isEmpty()) {
            $event->setBlocked(true, 'Cette session annulée comptait des inscrits, qui ont été remboursés : créez une nouvelle session plutôt que de la rouvrir.');
        }
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
            $event->setBlocked(true, 'Le test doit avoir au moins une compétence.');
            return;
        }

        if ($session->getScheduledExams()->isEmpty()) {
            $event->setBlocked(true, 'La session doit avoir au moins un examen planifié.');
            return;
        }

        // Check that all required (non-option) exams are scheduled
        $scheduledExamIds = [];
        foreach ($session->getScheduledExams() as $scheduledExam) {
            $exam = $scheduledExam->getExam();
            if ($exam) {
                $scheduledExamIds[] = $exam->getId();
            }
        }

        $missingExams = [];
        foreach ($assessment->getExams() as $exam) {
            if (!$exam->isOption() && !in_array($exam->getId(), $scheduledExamIds, true)) {
                $missingExams[] = $exam->getLabel();
            }
        }

        if (!empty($missingExams)) {
            $event->setBlocked(true, sprintf(
                'Toutes les épreuves obligatoires doivent être planifiées. Manquante(s) : %s.',
                implode(', ', $missingExams)
            ));
            return;
        }
    }

    /**
     * DRAFT → OPEN: auto-publish REGISTRATION_CONFIRMATION + REGISTRATION_CERTIFICATE.
     */
    public function onOpen(CompletedEvent $event): void
    {
        /** @var Session $session */
        $session = $event->getSubject();
        $this->autoPublishByCode($session, [
            DocumentAccessService::REGISTRATION_CONFIRMATION,
            DocumentAccessService::REGISTRATION_CERTIFICATE,
        ]);
    }

    /**
     * LOCKED → VALIDATED: auto-publish CONVOCATION, ATTENDANCE_CERTIFICATE, PAYMENT_CERTIFICATE
     * (la disponibilité réelle est ensuite filtrée par DocumentAccessService).
     */
    public function onValidate(CompletedEvent $event): void
    {
        /** @var Session $session */
        $session = $event->getSubject();
        $this->autoPublishByCode($session, [
            DocumentAccessService::CONVOCATION,
            DocumentAccessService::ATTENDANCE_CERTIFICATE,
            DocumentAccessService::PAYMENT_CERTIFICATE,
        ]);
    }

    /**
     * Any → CANCELLED: remove all auto-published documents.
     */
    public function onCancel(CompletedEvent $event): void
    {
        /** @var Session $session */
        $session = $event->getSubject();

        $autoPublications = $this->publicationRepository->findBy([
            'session' => $session,
            'isAutoPublished' => true,
        ]);

        foreach ($autoPublications as $publication) {
            $this->em->remove($publication);
        }

        $this->em->flush();
    }

    private function autoPublishByCode(Session $session, array $codes): void
    {
        foreach ($codes as $code) {
            $documentType = $this->documentTypeRepository->findOneBy(['code' => $code]);
            if (!$documentType) {
                continue;
            }

            // Check if already published
            $existing = $this->publicationRepository->findOneBy([
                'session' => $session,
                'documentType' => $documentType,
            ]);
            if ($existing) {
                continue;
            }

            $publication = new SessionDocumentPublication();
            $publication->setSession($session);
            $publication->setDocumentType($documentType);
            $publication->setIsAutoPublished(true);
            $publication->setPublishedAt(new \DateTimeImmutable());

            $this->em->persist($publication);
        }

        $this->em->flush();
    }
}
