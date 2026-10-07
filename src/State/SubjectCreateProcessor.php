<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ScheduledExam;
use App\Entity\Subject;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\PlatformRoleEnum;
use App\Enum\SubjectStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class SubjectCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Subject
    {
        /** @var Subject $subject */
        $subject = $data;

        // scheduledExam comes from the body (deserialized by API Platform)
        $scheduledExam = $subject->getScheduledExam();

        if (!$scheduledExam) {
            throw new UnprocessableEntityHttpException('Le champ scheduledExam est requis.');
        }

        // Reload to ensure it exists
        $scheduledExam = $this->entityManager->getRepository(ScheduledExam::class)->find($scheduledExam->getId());
        if (!$scheduledExam) {
            throw new NotFoundHttpException('ScheduledExam introuvable.');
        }

        // Check if a subject already exists for this scheduled exam
        $existing = $this->entityManager->getRepository(Subject::class)->findOneBy(['scheduledExam' => $scheduledExam]);
        if ($existing) {
            throw new UnprocessableEntityHttpException('Un sujet existe déjà pour cet examen planifié.');
        }

        /** @var User $currentUser */
        $currentUser = $this->security->getUser();

        if (!$this->canCreateSubject($currentUser, $scheduledExam)) {
            throw new AccessDeniedHttpException('Vous n\'avez pas les droits pour créer un sujet pour cet examen.');
        }

        $subject->setScheduledExam($scheduledExam);
        $subject->setStatus(SubjectStatusEnum::DRAFT);

        $this->entityManager->persist($subject);
        $this->entityManager->flush();

        return $subject;
    }

    private function canCreateSubject(User $user, ScheduledExam $scheduledExam): bool
    {
        if ($user->getPlatformRole() === PlatformRoleEnum::ADMIN) {
            return true;
        }

        $session = $scheduledExam->getSession();
        if (!$session) {
            return false;
        }

        $institute = $session->getInstitute();
        if (!$institute) {
            return false;
        }

        foreach ($institute->getMemberships() as $membership) {
            if ($membership->getUser()?->getId()?->equals($user->getId())
                && in_array($membership->getRole(), [InstituteRoleEnum::ADMIN, InstituteRoleEnum::TEACHER])
            ) {
                return true;
            }
        }

        return false;
    }
}
