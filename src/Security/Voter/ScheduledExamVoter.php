<?php

namespace App\Security\Voter;

use App\Entity\ScheduledExam;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\PlatformRoleEnum;
use App\Enum\SessionStatusEnum;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ScheduledExamVoter extends Voter
{
    public const SCHEDULED_EXAM_EDIT = 'SCHEDULED_EXAM_EDIT';
    public const SCHEDULED_EXAM_DELETE = 'SCHEDULED_EXAM_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
                self::SCHEDULED_EXAM_EDIT,
                self::SCHEDULED_EXAM_DELETE,
            ])
            && $subject instanceof ScheduledExam;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var ScheduledExam $scheduledExam */
        $scheduledExam = $subject;

        return match ($attribute) {
            self::SCHEDULED_EXAM_EDIT => $this->canEdit($token, $scheduledExam),
            self::SCHEDULED_EXAM_DELETE => $this->canDelete($token, $scheduledExam),
            default => false,
        };
    }

    private function canEdit(TokenInterface $token, ScheduledExam $scheduledExam): bool
    {
        $session = $scheduledExam->getSession();
        if (!$session) {
            return false;
        }

        // Allow editing in any status except CANCELLED (for examinator assignment on validated sessions)
        if ($session->getStatus() === SessionStatusEnum::CANCELLED) {
            return false;
        }

        return $this->isInstituteAdminOrPlatformAdmin($token, $scheduledExam);
    }

    private function canDelete(TokenInterface $token, ScheduledExam $scheduledExam): bool
    {
        $session = $scheduledExam->getSession();
        if (!$session) {
            return false;
        }

        // Suppression possible tant que la session n'est pas verrouillée/validée/annulée
        // (les inscriptions rattachées sont contrôlées par ScheduledExamDeleteProcessor → 409)
        if (!in_array($session->getStatus(), [SessionStatusEnum::DRAFT, SessionStatusEnum::OPEN], true)) {
            return false;
        }

        return $this->isInstituteAdminOrPlatformAdmin($token, $scheduledExam);
    }

    private function isInstituteAdminOrPlatformAdmin(TokenInterface $token, ScheduledExam $scheduledExam): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if ($user->getPlatformRole() === PlatformRoleEnum::ADMIN) {
            return true;
        }

        $institute = $scheduledExam->getSession()?->getInstitute();
        if (!$institute) {
            return false;
        }

        foreach ($institute->getMemberships() as $membership) {
            if ($membership->getUser()?->getId()?->equals($user->getId())
                && in_array($membership->getRole(), [InstituteRoleEnum::ADMIN, InstituteRoleEnum::STAFF], true)
                && $membership->isActive()
            ) {
                return true;
            }
        }

        return false;
    }
}
