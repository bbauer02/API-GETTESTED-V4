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
    /** Gestion des documents (modèles) : ADMIN ou STAFF actif */
    public const INSTITUTE_DOCUMENTS_MANAGE = 'INSTITUTE_DOCUMENTS_MANAGE';
    /** Lecture des contenus internes : ADMIN, STAFF ou TEACHER actif */
    public const INSTITUTE_STAFF_VIEW = 'INSTITUTE_STAFF_VIEW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
                self::INSTITUTE_VIEW,
                self::INSTITUTE_EDIT,
                self::INSTITUTE_DELETE,
                self::INSTITUTE_MANAGE_MEMBERS,
                self::INSTITUTE_DOCUMENTS_MANAGE,
                self::INSTITUTE_STAFF_VIEW,
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
            self::INSTITUTE_DOCUMENTS_MANAGE => $this->hasActiveRole($token, $institute, [InstituteRoleEnum::ADMIN, InstituteRoleEnum::STAFF]),
            self::INSTITUTE_STAFF_VIEW => $this->hasActiveRole($token, $institute, [InstituteRoleEnum::ADMIN, InstituteRoleEnum::STAFF, InstituteRoleEnum::TEACHER]),
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

    /**
     * @param InstituteRoleEnum[] $roles
     */
    private function hasActiveRole(TokenInterface $token, Institute $institute, array $roles): bool
    {
        if ($this->isPlatformAdmin($token)) {
            return true;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        foreach ($institute->getMemberships() as $membership) {
            if ($membership->isActive()
                && $membership->getUser()?->getId()?->equals($user->getId())
                && in_array($membership->getRole(), $roles, true)
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

    private function isInstituteAdmin(User $user, Institute $institute): bool
    {
        foreach ($institute->getMemberships() as $membership) {
            if ($membership->getUser()?->getId()?->equals($user->getId())
                && $membership->getRole() === InstituteRoleEnum::ADMIN
                && $membership->isActive()
            ) {
                return true;
            }
        }

        return false;
    }
}
