<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Institute;
use App\Entity\InstituteMembership;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\PlatformRoleEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InstituteMembershipProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $instituteId = $uriVariables['instituteId'] ?? null;
        $institute = $this->entityManager->getRepository(Institute::class)->find($instituteId);

        if (!$institute) {
            throw new NotFoundHttpException('Institut introuvable.');
        }

        $currentUser = $this->security->getUser();

        if (!$currentUser instanceof User || !$this->canViewMembers($currentUser, $institute)) {
            throw new AccessDeniedHttpException('Vous n\'avez pas les droits pour voir les membres de cet institut.');
        }

        return $this->entityManager->getRepository(InstituteMembership::class)->findBy([
            'institute' => $institute,
        ]);
    }

    private function canViewMembers(User $user, Institute $institute): bool
    {
        if ($user->getPlatformRole() === PlatformRoleEnum::ADMIN) {
            return true;
        }

        // Personnel actif uniquement : un candidat (CUSTOMER) ne voit pas les coordonnées des autres membres
        foreach ($institute->getMemberships() as $membership) {
            if ($membership->getUser()?->getId()?->equals($user->getId())
                && $membership->isActive()
                && in_array($membership->getRole(), [InstituteRoleEnum::ADMIN, InstituteRoleEnum::STAFF, InstituteRoleEnum::TEACHER], true)
            ) {
                return true;
            }
        }

        return false;
    }
}
