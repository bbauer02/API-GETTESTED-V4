<?php

namespace App\Security\Voter;

use App\Entity\Assessment;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\OwnershipTypeEnum;
use App\Enum\PlatformRoleEnum;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class AssessmentVoter extends Voter
{
    public const ASSESSMENT_EDIT = 'ASSESSMENT_EDIT';
    public const ASSESSMENT_DELETE = 'ASSESSMENT_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
                self::ASSESSMENT_EDIT,
                self::ASSESSMENT_DELETE,
            ])
            && $subject instanceof Assessment;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var Assessment $assessment */
        $assessment = $subject;

        return match ($attribute) {
            self::ASSESSMENT_EDIT => $this->canEdit($token, $assessment),
            self::ASSESSMENT_DELETE => $this->isPlatformAdmin($token),
            default => false,
        };
    }

    private function canEdit(TokenInterface $token, Assessment $assessment): bool
    {
        if ($this->isPlatformAdmin($token)) {
            return true;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $this->isOwnerInstituteAdmin($user, $assessment);
    }

    private function isPlatformAdmin(TokenInterface $token): bool
    {
        $user = $token->getUser();

        return $user instanceof User && $user->getPlatformRole() === PlatformRoleEnum::ADMIN;
    }

    private function isOwnerInstituteAdmin(User $user, Assessment $assessment): bool
    {
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
