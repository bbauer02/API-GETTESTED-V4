<?php

namespace App\Security\Voter;

use App\Entity\Subject;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\PlatformRoleEnum;
use App\Enum\SubjectStatusEnum;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class SubjectVoter extends Voter
{
    public const SUBJECT_EDIT = 'SUBJECT_EDIT';
    public const SUBJECT_DELETE = 'SUBJECT_DELETE';
    public const SUBJECT_LOCK = 'SUBJECT_LOCK';
    public const SUBJECT_ARCHIVE = 'SUBJECT_ARCHIVE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
                self::SUBJECT_EDIT,
                self::SUBJECT_DELETE,
                self::SUBJECT_LOCK,
                self::SUBJECT_ARCHIVE,
            ])
            && $subject instanceof Subject;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var Subject $subjectEntity */
        $subjectEntity = $subject;

        return match ($attribute) {
            self::SUBJECT_EDIT => $this->canEdit($token, $subjectEntity),
            self::SUBJECT_DELETE => $this->canDelete($token, $subjectEntity),
            self::SUBJECT_LOCK => $this->canLock($token, $subjectEntity),
            self::SUBJECT_ARCHIVE => $this->canArchive($token, $subjectEntity),
            default => false,
        };
    }

    private function canEdit(TokenInterface $token, Subject $subject): bool
    {
        // Only DRAFT subjects can be edited
        if ($subject->getStatus() !== SubjectStatusEnum::DRAFT) {
            return false;
        }

        return $this->canManage($token, $subject);
    }

    private function canDelete(TokenInterface $token, Subject $subject): bool
    {
        // Only DRAFT subjects can be deleted
        if ($subject->getStatus() !== SubjectStatusEnum::DRAFT) {
            return false;
        }

        return $this->canManage($token, $subject);
    }

    private function canLock(TokenInterface $token, Subject $subject): bool
    {
        if ($subject->getStatus() !== SubjectStatusEnum::DRAFT) {
            return false;
        }

        return $this->canManage($token, $subject);
    }

    private function canArchive(TokenInterface $token, Subject $subject): bool
    {
        if ($subject->getStatus() !== SubjectStatusEnum::LOCKED) {
            return false;
        }

        return $this->canManage($token, $subject);
    }

    private function canManage(TokenInterface $token, Subject $subject): bool
    {
        if ($this->isPlatformAdmin($token)) {
            return true;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $this->isSessionInstituteAdmin($user, $subject);
    }

    private function isPlatformAdmin(TokenInterface $token): bool
    {
        $user = $token->getUser();
        return $user instanceof User && $user->getPlatformRole() === PlatformRoleEnum::ADMIN;
    }

    /**
     * Navigate: Subject → ScheduledExam → Session → Institute → Memberships
     */
    private function isSessionInstituteAdmin(User $user, Subject $subject): bool
    {
        $scheduledExam = $subject->getScheduledExam();
        if (!$scheduledExam) {
            return false;
        }

        $session = $scheduledExam->getSession();
        if (!$session) {
            return false;
        }

        $institute = $session->getInstitute();
        if (!$institute) {
            return false;
        }

        foreach ($institute->getMemberships() as $membership) {
            if ($membership->getUser()?->getId()?->equals($user->getId())
                && in_array($membership->getRole(), [InstituteRoleEnum::ADMIN, InstituteRoleEnum::TEACHER])
            ) {
                return true;
            }
        }

        return false;
    }
}
