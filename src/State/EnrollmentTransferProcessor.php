<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\EnrollmentTransferInput;
use App\Entity\EnrollmentSession;
use App\Entity\ScheduledExam;
use App\Entity\Session;
use App\Enum\SessionStatusEnum;
use App\Exception\ConflictHttpException;
use App\Security\Voter\EnrollmentVoter;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\CandidateNotifier;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * POST /api/enrollment-sessions/{id}/transfer  body { targetSessionId }
 *
 * Déplace une inscription vers une autre session OPEN du même institut et du même assessment.
 * Chaque EnrollmentExam est rattaché au ScheduledExam du même Exam dans la session cible.
 * Les factures restent attachées à l'inscription (pas de re-facturation).
 */
class EnrollmentTransferProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CandidateNotifier $candidateNotifier,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): EnrollmentSession
    {
        /** @var EnrollmentTransferInput $input */
        $input = $data;

        $enrollment = $this->entityManager->getRepository(EnrollmentSession::class)->find($uriVariables['id'] ?? null);
        if (!$enrollment) {
            throw new NotFoundHttpException('Inscription introuvable.');
        }

        if (!$this->security->isGranted(EnrollmentVoter::ENROLLMENT_MANAGE, $enrollment)) {
            throw new AccessDeniedHttpException("Vous n'avez pas les droits pour transférer cette inscription.");
        }

        if (!$input->targetSessionId || !Uuid::isValid($input->targetSessionId)) {
            throw new UnprocessableEntityHttpException('Le champ "targetSessionId" est requis (uuid).');
        }

        $target = $this->entityManager->getRepository(Session::class)->find($input->targetSessionId);
        if (!$target) {
            throw new NotFoundHttpException('Session cible introuvable.');
        }

        $source = $enrollment->getSession();
        if ($source && $source->getId()?->equals($target->getId())) {
            throw new ConflictHttpException("L'inscription est déjà rattachée à cette session.");
        }

        if (!$target->getInstitute()?->getId()?->equals($source?->getInstitute()?->getId())) {
            throw new UnprocessableEntityHttpException('La session cible doit appartenir au même institut.');
        }

        if (!$target->getAssessment()?->getId()?->equals($source?->getAssessment()?->getId())) {
            throw new UnprocessableEntityHttpException('La session cible doit porter sur le même test.');
        }

        if ($target->getStatus() !== SessionStatusEnum::OPEN) {
            throw new ConflictHttpException("La session cible n'est pas ouverte aux inscriptions.");
        }

        $placesAvailable = $target->getPlacesAvailable();
        if ($placesAvailable !== null) {
            $count = $this->entityManager->getRepository(EnrollmentSession::class)->count(['session' => $target]);
            if ($count >= $placesAvailable) {
                throw new ConflictHttpException('Plus de places disponibles dans la session cible.');
            }
        }

        $existing = $this->entityManager->getRepository(EnrollmentSession::class)
            ->findOneBy(['session' => $target, 'user' => $enrollment->getUser()]);
        if ($existing) {
            throw new ConflictHttpException('Le candidat est déjà inscrit à la session cible.');
        }

        // Correspondance Exam → ScheduledExam dans la session cible
        $targetByExam = [];
        foreach ($target->getScheduledExams() as $scheduledExam) {
            $examId = $scheduledExam->getExam()?->getId()?->toRfc4122();
            if ($examId) {
                $targetByExam[$examId] = $scheduledExam;
            }
        }

        $missing = [];
        $mapping = [];
        foreach ($enrollment->getEnrollmentExams() as $enrollmentExam) {
            $exam = $enrollmentExam->getScheduledExam()?->getExam();
            $examId = $exam?->getId()?->toRfc4122();
            if (!$examId || !isset($targetByExam[$examId])) {
                $missing[] = $exam?->getLabel() ?? 'Épreuve inconnue';
                continue;
            }
            $mapping[] = [$enrollmentExam, $targetByExam[$examId]];
        }

        if (!empty($missing)) {
            throw new UnprocessableEntityHttpException(sprintf(
                'La session cible ne planifie pas toutes les épreuves de l\'inscription. Manquante(s) : %s.',
                implode(', ', array_unique($missing))
            ));
        }

        /** @var array{0: \App\Entity\EnrollmentExam, 1: ScheduledExam} $pair */
        foreach ($mapping as $pair) {
            $pair[0]->setScheduledExam($pair[1]);
        }
        $previousSession = $source;
        $enrollment->setSession($target);

        $this->entityManager->flush();

        if ($previousSession) {
            $this->candidateNotifier->enrollmentTransferred($enrollment, $previousSession);
        }

        return $enrollment;
    }
}
