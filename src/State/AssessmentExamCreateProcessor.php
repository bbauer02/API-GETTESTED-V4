<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Assessment;
use App\Entity\Exam;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\OwnershipTypeEnum;
use App\Enum\PlatformRoleEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AssessmentExamCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Exam
    {
        /** @var Exam $exam */
        $exam = $data;

        $assessmentId = $uriVariables['assessmentId'] ?? null;
        $assessment = $this->entityManager->getRepository(Assessment::class)->find($assessmentId);

        if (!$assessment) {
            throw new NotFoundHttpException('Assessment introuvable.');
        }

        /** @var User $currentUser */
        $currentUser = $this->security->getUser();

        if (!$this->canEditAssessment($currentUser, $assessment)) {
            throw new AccessDeniedHttpException('Vous n\'avez pas les droits pour ajouter un exam à cet assessment.');
        }

        $exam->setAssessment($assessment);

        $this->entityManager->persist($exam);
        $this->entityManager->flush();

        return $exam;
    }

    private function canEditAssessment(User $user, Assessment $assessment): bool
    {
        if ($user->getPlatformRole() === PlatformRoleEnum::ADMIN) {
            return true;
        }

        foreach ($assessment->getOwnerships() as $ownership) {
            if ($ownership->getOwnershipType() !== OwnershipTypeEnum::OWNER) {
                continue;
            }

            $institute = $ownership->getInstitute();
            if ($institute === null) {
                continue;
            }

            foreach ($institute->getMemberships() as $membership) {
                if ($membership->getUser()?->getId()?->equals($user->getId())
                    && $membership->getRole() === InstituteRoleEnum::ADMIN
                ) {
                    return true;
                }
            }
        }

        return false;
    }
}
