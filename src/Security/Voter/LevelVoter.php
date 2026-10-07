<?php

namespace App\Security\Voter;

use App\Entity\Assessment;
use App\Entity\Level;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\OwnershipTypeEnum;
use App\Enum\PlatformRoleEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class LevelVoter extends Voter
{
    public const LEVEL_EDIT = 'LEVEL_EDIT';
    public const LEVEL_DELETE = 'LEVEL_DELETE';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
                self::LEVEL_EDIT,
                self::LEVEL_DELETE,
            ])
            && $subject instanceof Level;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var Level $level */
        $level = $subject;

        return match ($attribute) {
            self::LEVEL_EDIT => $this->canEdit($token, $level),
            self::LEVEL_DELETE => $this->canDelete($token, $level),
            default => false,
        };
    }

    private function canEdit(TokenInterface $token, Level $level): bool
    {
        if ($this->isPlatformAdmin($token)) {
            return true;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $this->isOwnerInstituteAdmin($user, $level);
    }

    private function canDelete(TokenInterface $token, Level $level): bool
    {
        if ($this->isPlatformAdmin($token)) {
            return true;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $this->isOwnerInstituteAdmin($user, $level);
    }

    private function isPlatformAdmin(TokenInterface $token): bool
    {
        $user = $token->getUser();

        return $user instanceof User && $user->getPlatformRole() === PlatformRoleEnum::ADMIN;
    }

    private function isOwnerInstituteAdmin(User $user, Level $level): bool
    {
        // Find assessments that use this level via the assessment_level join table
        $assessments = $this->entityManager->getRepository(Assessment::class)
            ->createQueryBuilder('a')
            ->innerJoin('a.levels', 'l')
            ->where('l.id = :levelId')
            ->setParameter('levelId', $level->getId(), 'uuid')
            ->getQuery()
            ->getResult();

        foreach ($assessments as $assessment) {
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
        }

        return false;
    }
}
