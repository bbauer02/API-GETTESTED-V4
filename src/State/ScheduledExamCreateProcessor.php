<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ScheduledExam;
use App\Entity\Session;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\PlatformRoleEnum;
use App\Enum\SessionValidationEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ScheduledExamCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ScheduledExam
    {
        /** @var ScheduledExam $scheduledExam */
        $scheduledExam = $data;

        $sessionId = $uriVariables['sessionId'] ?? null;
        $session = $this->entityManager->getRepository(Session::class)->find($sessionId);

        if (!$session) {
            throw new NotFoundHttpException('Session introuvable.');
        }

        if (!in_array($session->getValidation(), [SessionValidationEnum::DRAFT, SessionValidationEnum::OPEN])) {
            throw new UnprocessableEntityHttpException('Les examens planifiés ne peuvent être ajoutés qu\'aux sessions DRAFT ou OPEN.');
        }

        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        if (!$this->canCreateScheduledExam($currentUser, $session)) {
            throw new AccessDeniedHttpException('Vous n\'avez pas les droits pour ajouter un examen planifié à cette session.');
        }

        $exam = $scheduledExam->getExam();
        if ($exam && $session->getAssessment()) {
            if (!$exam->getAssessment()?->getId()?->equals($session->getAssessment()->getId())) {
                throw new UnprocessableEntityHttpException('L\'examen doit appartenir au même assessment que la session.');
            }
        }

        if ($scheduledExam->getStartDate() && $session->getStart() && $session->getEnd()) {
            if ($scheduledExam->getStartDate() < $session->getStart() || $scheduledExam->getStartDate() > $session->getEnd()) {
                throw new UnprocessableEntityHttpException('La date de début de l\'examen doit être dans la plage de la session.');
            }
        }

        $scheduledExam->setSession($session);

        $this->entityManager->persist($scheduledExam);
        $this->entityManager->flush();

        return $scheduledExam;
    }

    private function canCreateScheduledExam(User $user, Session $session): bool
    {
        if ($user->getPlatformRole() === PlatformRoleEnum::ADMIN) {
            return true;
        }

        $institute = $session->getInstitute();
        if (!$institute) {
            return false;
        }

        foreach ($institute->getMemberships() as $membership) {
            if ($membership->getUser()?->getId()?->equals($user->getId())
                && $membership->getRole() === InstituteRoleEnum::ADMIN
            ) {
                return true;
            }
        }

        return false;
    }
}
