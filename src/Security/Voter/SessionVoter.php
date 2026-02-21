<?php

namespace App\Security\Voter;

use App\Entity\Session;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\PlatformRoleEnum;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class SessionVoter extends Voter
{
    public const SESSION_EDIT = 'SESSION_EDIT';
    public const SESSION_DELETE = 'SESSION_DELETE';
    public const SESSION_TRANSITION = 'SESSION_TRANSITION';
    public const SESSION_VIEW_ALL = 'SESSION_VIEW_ALL';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
                self::SESSION_EDIT,
                self::SESSION_DELETE,
                self::SESSION_TRANSITION,
                self::SESSION_VIEW_ALL,
            ])
            && $subject instanceof Session;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var Session $session */
        $session = $subject;

        return match ($attribute) {
            self::SESSION_EDIT, self::SESSION_DELETE, self::SESSION_TRANSITION => $this->canManage($token, $session),
            self::SESSION_VIEW_ALL => $this->canViewAll($token, $session),
            default => false,
        };
    }

    private function canManage(TokenInterface $token, Session $session): bool
    {
        if ($this->isPlatformAdmin($token)) {
            return true;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $this->isInstituteAdmin($user, $session);
    }

    private function canViewAll(TokenInterface $token, Session $session): bool
    {
        if ($this->isPlatformAdmin($token)) {
            return true;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $institute = $session->getInstitute();
        if (!$institute) {
            return false;
        }

        foreach ($institute->getMemberships() as $membership) {
            if ($membership->getUser()?->getId()?->equals($user->getId())
                && in_array($membership->getRole(), [InstituteRoleEnum::ADMIN, InstituteRoleEnum::STAFF])
            ) {
                return true;
            }
        }

        return false;
    }

    private function isPlatformAdmin(TokenInterface $token): bool
    {
        $user = $token->getUser();

        return $user instanceof User && $user->getPlatformRole() === PlatformRoleEnum::ADMIN;
    }

    private function isInstituteAdmin(User $user, Session $session): bool
    {
        $institute = $session->getInstitute();
        if (!$institute) {
            return false;
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
