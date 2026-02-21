<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Assessment;
use App\Entity\AssessmentOwnership;
use App\Entity\Institute;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\OwnershipTypeEnum;
use App\Enum\PlatformRoleEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InstituteAssessmentCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Assessment
    {
        /** @var Assessment $assessment */
        $assessment = $data;

        $instituteId = $uriVariables['instituteId'] ?? null;
        $institute = $this->entityManager->getRepository(Institute::class)->find($instituteId);

        if (!$institute) {
            throw new NotFoundHttpException('Institut introuvable.');
        }

        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        if (!$this->canCreateAssessment($currentUser, $institute)) {
            throw new AccessDeniedHttpException('Vous n\'avez pas les droits pour créer un test dans cet institut.');
        }

        $assessment->setIsInternal(false);

        $this->entityManager->persist($assessment);

        $ownership = new AssessmentOwnership();
        $ownership->setInstitute($institute);
        $ownership->setAssessment($assessment);
        $ownership->setOwnershipType(OwnershipTypeEnum::OWNER);
        $ownership->setRelationshipDate(new \DateTime());
        $ownership->setUser($currentUser);

        $this->entityManager->persist($ownership);
        $this->entityManager->flush();

        return $assessment;
    }

    private function canCreateAssessment(User $user, Institute $institute): bool
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
}
