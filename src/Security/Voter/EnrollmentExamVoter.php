<?php

namespace App\Security\Voter;

use App\Entity\EnrollmentExam;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\PlatformRoleEnum;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class EnrollmentExamVoter extends Voter
{
    public const ENROLLMENT_EXAM_VIEW = 'ENROLLMENT_EXAM_VIEW';
    public const ENROLLMENT_EXAM_SCORE = 'ENROLLMENT_EXAM_SCORE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::ENROLLMENT_EXAM_VIEW, self::ENROLLMENT_EXAM_SCORE])
            && $subject instanceof EnrollmentExam;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var EnrollmentExam $enrollmentExam */
        $enrollmentExam = $subject;

        return match ($attribute) {
            self::ENROLLMENT_EXAM_VIEW => $this->canView($user, $enrollmentExam),
            self::ENROLLMENT_EXAM_SCORE => $this->canScore($user, $enrollmentExam),
            default => false,
        };
    }

    private function canView(User $user, EnrollmentExam $enrollmentExam): bool
    {
        if ($user->getPlatformRole() === PlatformRoleEnum::ADMIN) {
            return true;
        }

        $enrollmentUser = $enrollmentExam->getEnrollmentSession()?->getUser();
        if ($enrollmentUser?->getId()?->equals($user->getId())) {
            return true;
        }

        return $this->hasInstituteRole($user, $enrollmentExam, [
            InstituteRoleEnum::ADMIN,
            InstituteRoleEnum::STAFF,
            InstituteRoleEnum::TEACHER,
        ]);
    }

    private function canScore(User $user, EnrollmentExam $enrollmentExam): bool
    {
        if ($user->getPlatformRole() === PlatformRoleEnum::ADMIN) {
            return true;
        }

        // L'utilisateur est examinateur du scheduledExam associé
        $scheduledExam = $enrollmentExam->getScheduledExam();
        if ($scheduledExam) {
            foreach ($scheduledExam->getExaminators() as $examinator) {
                if ($examinator->getId()?->equals($user->getId())) {
                    return true;
                }
            }
        }

        // Institute ADMIN
        return $this->hasInstituteRole($user, $enrollmentExam, [InstituteRoleEnum::ADMIN]);
    }

    private function hasInstituteRole(User $user, EnrollmentExam $enrollmentExam, array $roles): bool
    {
        $institute = $enrollmentExam->getEnrollmentSession()?->getSession()?->getInstitute();
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
