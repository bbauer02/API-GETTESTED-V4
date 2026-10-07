<?php

namespace App\Security\Voter;

use App\Entity\ExamCenter;
use App\Entity\Institute;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\PlatformRoleEnum;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ExamCenterVoter extends Voter
{
    public const EXAM_CENTER_VIEW = 'EXAM_CENTER_VIEW';
    public const EXAM_CENTER_EDIT = 'EXAM_CENTER_EDIT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EXAM_CENTER_VIEW, self::EXAM_CENTER_EDIT])
            && $subject instanceof ExamCenter;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var ExamCenter $examCenter */
        $examCenter = $subject;
        $institute = $examCenter->getInstitute();
        if (!$institute) {
            return false;
        }

        return match ($attribute) {
            self::EXAM_CENTER_VIEW => self::canView($user, $institute),
            self::EXAM_CENTER_EDIT => self::canEdit($user, $institute),
            default => false,
        };
    }

    /** Tout membre actif de l'institut (ou admin plateforme). */
    public static function canView(User $user, Institute $institute): bool
    {
        if ($user->getPlatformRole() === PlatformRoleEnum::ADMIN) {
            return true;
        }

        foreach ($institute->getMemberships() as $membership) {
            if ($membership->getUser()?->getId()?->equals($user->getId()) && $membership->isActive()) {
                return true;
            }
        }

        return false;
    }

    /** Membre actif ADMIN ou STAFF (ou admin plateforme). */
    public static function canEdit(User $user, Institute $institute): bool
    {
        if ($user->getPlatformRole() === PlatformRoleEnum::ADMIN) {
            return true;
        }

        foreach ($institute->getMemberships() as $membership) {
            if ($membership->getUser()?->getId()?->equals($user->getId())
                && $membership->isActive()
                && in_array($membership->getRole(), [InstituteRoleEnum::ADMIN, InstituteRoleEnum::STAFF], true)
            ) {
                return true;
            }
        }

        return false;
    }
}
