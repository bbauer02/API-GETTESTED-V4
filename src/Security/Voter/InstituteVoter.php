<?php

namespace App\Security\Voter;

use App\Entity\Institute;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\PlatformRoleEnum;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class InstituteVoter extends Voter
{
    public const INSTITUTE_VIEW = 'INSTITUTE_VIEW';
    public const INSTITUTE_EDIT = 'INSTITUTE_EDIT';
    public const INSTITUTE_DELETE = 'INSTITUTE_DELETE';
    public const INSTITUTE_MANAGE_MEMBERS = 'INSTITUTE_MANAGE_MEMBERS';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
                self::INSTITUTE_VIEW,
                self::INSTITUTE_EDIT,
                self::INSTITUTE_DELETE,
                self::INSTITUTE_MANAGE_MEMBERS,
            ])
            && $subject instanceof Institute;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var Institute $institute */
        $institute = $subject;

        return match ($attribute) {
            self::INSTITUTE_VIEW => true,
            self::INSTITUTE_EDIT, self::INSTITUTE_MANAGE_MEMBERS => $this->canManage($token, $institute),
            self::INSTITUTE_DELETE => $this->isPlatformAdmin($token),
            default => false,
        };
    }

    private function canManage(TokenInterface $token, Institute $institute): bool
    {
        if ($this->isPlatformAdmin($token)) {
            return true;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $this->isInstituteAdmin($user, $institute);
    }

    private function isPlatformAdmin(TokenInterface $token): bool
    {
        $user = $token->getUser();

        return $user instanceof User && $user->getPlatformRole() === PlatformRoleEnum::ADMIN;
    }

    private function isInstituteAdmin(User $user, Institute $institute): bool
    {
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
