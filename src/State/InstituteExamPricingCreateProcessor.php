<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Institute;
use App\Entity\InstituteExamPricing;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\OwnershipTypeEnum;
use App\Enum\PlatformRoleEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InstituteExamPricingCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): InstituteExamPricing
    {
        /** @var InstituteExamPricing $pricing */
        $pricing = $data;

        $instituteId = $uriVariables['instituteId'] ?? null;
        $institute = $this->entityManager->getRepository(Institute::class)->find($instituteId);

        if (!$institute) {
            throw new NotFoundHttpException('Institut introuvable.');
        }

        /** @var User $currentUser */
        $currentUser = $this->security->getUser();

        if (!$this->isInstituteAdmin($currentUser, $institute)) {
            throw new AccessDeniedHttpException('Vous n\'avez pas les droits pour gérer les tarifs de cet institut.');
        }

        // Vérifier que l'institut a accès à l'exam via OWNER ou BUYER
        $exam = $pricing->getExam();
        if ($exam === null) {
            throw new NotFoundHttpException('Exam introuvable.');
        }

        $assessment = $exam->getAssessment();
        if ($assessment === null || !$this->hasAccessToAssessment($institute, $assessment)) {
            throw new AccessDeniedHttpException('Cet institut n\'a pas accès à cet assessment.');
        }

        $pricing->setInstitute($institute);

        $this->entityManager->persist($pricing);
        $this->entityManager->flush();

        return $pricing;
    }

    private function isInstituteAdmin(User $user, Institute $institute): bool
    {
        if ($user->getPlatformRole() === PlatformRoleEnum::ADMIN) {
            return true;
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

    private function hasAccessToAssessment(Institute $institute, \App\Entity\Assessment $assessment): bool
    {
        foreach ($assessment->getOwnerships() as $ownership) {
            if ($ownership->getInstitute()?->getId()?->equals($institute->getId())) {
                return true;
            }
        }

        return false;
    }
}
