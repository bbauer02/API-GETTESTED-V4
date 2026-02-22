<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Institute;
use App\Entity\InstituteMembership;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\PlatformRoleEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use App\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class MembershipInviteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): InstituteMembership
    {
        /** @var InstituteMembership $membership */
        $membership = $data;

        $instituteId = $uriVariables['instituteId'] ?? null;
        $institute = $this->entityManager->getRepository(Institute::class)->find($instituteId);

        if (!$institute) {
            throw new NotFoundHttpException('Institut introuvable.');
        }

        // Vérifier les droits : PLATFORM_ADMIN ou INSTITUTE_ADMIN
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        if (!$this->canManageMembers($currentUser, $institute)) {
            throw new AccessDeniedHttpException('Vous n\'avez pas les droits pour gérer les membres de cet institut.');
        }

        // Vérifier que le user cible n'est pas déjà membre
        $targetUser = $membership->getUser();
        if ($targetUser === null) {
            throw new UnprocessableEntityHttpException('L\'utilisateur cible est requis.');
        }

        $existingMembership = $this->entityManager->getRepository(InstituteMembership::class)->findOneBy([
            'user' => $targetUser,
            'institute' => $institute,
        ]);

        if ($existingMembership !== null) {
            throw new ConflictHttpException('Cet utilisateur est déjà membre de cet institut.');
        }

        $membership->setInstitute($institute);
        $membership->setSince(new \DateTime());

        $this->entityManager->persist($membership);
        $this->entityManager->flush();

        return $membership;
    }

    private function canManageMembers(User $user, Institute $institute): bool
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
