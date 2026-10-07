<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Exam;
use App\Entity\Skill;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\OwnershipTypeEnum;
use App\Enum\PlatformRoleEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ExamSkillCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Skill
    {
        /** @var Skill $skill */
        $skill = $data;

        $examId = $uriVariables['examId'] ?? null;
        $exam = $this->entityManager->getRepository(Exam::class)->find($examId);

        if (!$exam) {
            throw new NotFoundHttpException('Exam introuvable.');
        }

        /** @var User $currentUser */
        $currentUser = $this->security->getUser();

        if (!$this->canEditAssessment($currentUser, $exam->getAssessment())) {
            throw new AccessDeniedHttpException('Vous n\'avez pas les droits pour ajouter un skill à cet exam.');
        }

        $this->entityManager->persist($skill);

        // Link skill to the exam
        $exam->addSkill($skill);

        // Also link skill to the assessment
        $assessment = $exam->getAssessment();
        if ($assessment !== null) {
            $assessment->addSkill($skill);
        }

        $this->entityManager->flush();

        return $skill;
    }

    private function canEditAssessment(User $user, ?\App\Entity\Assessment $assessment): bool
    {
        if ($assessment === null) {
            return false;
        }

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
