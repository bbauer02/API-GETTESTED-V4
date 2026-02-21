<?php

namespace App\Security\Voter;

use App\Entity\EnrollmentSession;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\PlatformRoleEnum;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class EnrollmentVoter extends Voter
{
    public const ENROLLMENT_VIEW = 'ENROLLMENT_VIEW';
    public const ENROLLMENT_CANCEL = 'ENROLLMENT_CANCEL';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::ENROLLMENT_VIEW, self::ENROLLMENT_CANCEL])
            && $subject instanceof EnrollmentSession;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var EnrollmentSession $enrollment */
        $enrollment = $subject;

        return match ($attribute) {
            self::ENROLLMENT_VIEW => $this->canView($user, $enrollment),
            self::ENROLLMENT_CANCEL => $this->canCancel($user, $enrollment),
            default => false,
        };
    }

    private function canView(User $user, EnrollmentSession $enrollment): bool
    {
        if ($user->getPlatformRole() === PlatformRoleEnum::ADMIN) {
            return true;
        }

        if ($enrollment->getUser()?->getId()?->equals($user->getId())) {
            return true;
        }

        return $this->hasInstituteRole($user, $enrollment, [
            InstituteRoleEnum::ADMIN,
            InstituteRoleEnum::STAFF,
            InstituteRoleEnum::TEACHER,
        ]);
    }

    private function canCancel(User $user, EnrollmentSession $enrollment): bool
    {
        if ($user->getPlatformRole() === PlatformRoleEnum::ADMIN) {
            return true;
        }

        if ($enrollment->getUser()?->getId()?->equals($user->getId())) {
            return true;
        }

        return $this->hasInstituteRole($user, $enrollment, [InstituteRoleEnum::ADMIN]);
    }

    private function hasInstituteRole(User $user, EnrollmentSession $enrollment, array $roles): bool
    {
        $institute = $enrollment->getSession()?->getInstitute();
        if (!$institute) {
            return false;
        }

        foreach ($institute->getMemberships() as $membership) {
            if ($membership->getUser()?->getId()?->equals($user->getId())
                && in_array($membership->getRole(), $roles)
            ) {
                return true;
            }
        }

        return false;
    }
}
