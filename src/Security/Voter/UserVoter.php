<?php

namespace App\Security\Voter;

use App\Entity\User;
use App\Enum\PlatformRoleEnum;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class UserVoter extends Voter
{
    public const VIEW_SELF = 'VIEW_SELF';
    public const EDIT_SELF = 'EDIT_SELF';
    public const ADMIN_ACCESS = 'ADMIN_ACCESS';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW_SELF, self::EDIT_SELF, self::ADMIN_ACCESS])
            && ($subject instanceof User || $subject === null);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $currentUser = $token->getUser();
        if (!$currentUser instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::VIEW_SELF, self::EDIT_SELF => $subject instanceof User && $currentUser->getId()?->equals($subject->getId()),
            self::ADMIN_ACCESS => $currentUser->getPlatformRole() === PlatformRoleEnum::ADMIN,
            default => false,
        };
    }
}
