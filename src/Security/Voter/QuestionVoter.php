<?php

namespace App\Security\Voter;

use App\Entity\Question;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\OwnershipTypeEnum;
use App\Enum\PlatformRoleEnum;
use App\Enum\QuestionStatusEnum;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class QuestionVoter extends Voter
{
    public const QUESTION_CREATE = 'QUESTION_CREATE';
    public const QUESTION_EDIT = 'QUESTION_EDIT';
    public const QUESTION_DELETE = 'QUESTION_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        // QUESTION_CREATE est évalué après la désérialisation (securityPostDenormalize) :
        // le sujet est la question à créer, avec son test déjà renseigné.
        if ($attribute === self::QUESTION_CREATE) {
            return true;
        }

        return in_array($attribute, [
                self::QUESTION_EDIT,
                self::QUESTION_DELETE,
            ])
            && $subject instanceof Question;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        return match ($attribute) {
            self::QUESTION_CREATE => $this->canCreate($token, $subject),
            self::QUESTION_EDIT => $this->canEdit($token, $subject),
            self::QUESTION_DELETE => $this->canDelete($token, $subject),
            default => false,
        };
    }

    /**
     * Création : administrateur plateforme, ou ADMIN / TEACHER actif d'un institut
     * propriétaire (OWNER) du test de la question.
     */
    private function canCreate(TokenInterface $token, mixed $question): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if ($user->getPlatformRole() === PlatformRoleEnum::ADMIN) {
            return true;
        }

        if (!$question instanceof Question) {
            return false;
        }

        return $this->isAssessmentOwnerMember($user, $question);
    }

    private function canEdit(TokenInterface $token, Question $question): bool
    {
        if ($this->isPlatformAdmin($token)) {
            return true;
        }

        // DRAFT et PRETESTING : modifiables entièrement
        // CALIBRATED : seuls le libellé et la consigne (QuestionPatchProcessor)
        // RETIRED: not editable at all
        if ($question->getStatus() === QuestionStatusEnum::RETIRED) {
            return false;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $this->isAssessmentOwnerMember($user, $question);
    }

    private function canDelete(TokenInterface $token, Question $question): bool
    {
        // Only DRAFT questions with 0 administrations can be deleted
        if ($question->getStatus() !== QuestionStatusEnum::DRAFT || $question->getTimesAdministered() > 0) {
            return false;
        }

        if ($this->isPlatformAdmin($token)) {
            return true;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $this->isAssessmentOwnerAdmin($user, $question);
    }

    private function isPlatformAdmin(TokenInterface $token): bool
    {
        $user = $token->getUser();
        return $user instanceof User && $user->getPlatformRole() === PlatformRoleEnum::ADMIN;
    }

    /**
     * Check if user is ADMIN or TEACHER in an institute that OWNS the question's assessment
     */
    private function isAssessmentOwnerMember(User $user, Question $question): bool
    {
        $assessment = $question->getAssessment();
        if (!$assessment) {
            return false;
        }

        foreach ($assessment->getOwnerships() as $ownership) {
            if ($ownership->getOwnershipType() !== OwnershipTypeEnum::OWNER) {
                continue;
            }

            $institute = $ownership->getInstitute();
            if ($institute === null) {
                continue;
            }

            foreach ($institute->getMemberships() as $membership) {
                if ($membership->isActive()
                    && $membership->getUser()?->getId()?->equals($user->getId())
                    && in_array($membership->getRole(), [InstituteRoleEnum::ADMIN, InstituteRoleEnum::TEACHER])
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if user is ADMIN in an institute that OWNS the question's assessment
     */
    private function isAssessmentOwnerAdmin(User $user, Question $question): bool
    {
        $assessment = $question->getAssessment();
        if (!$assessment) {
            return false;
        }

        foreach ($assessment->getOwnerships() as $ownership) {
            if ($ownership->getOwnershipType() !== OwnershipTypeEnum::OWNER) {
                continue;
            }

            $institute = $ownership->getInstitute();
            if ($institute === null) {
                continue;
            }

            foreach ($institute->getMemberships() as $membership) {
                if ($membership->isActive()
                    && $membership->getUser()?->getId()?->equals($user->getId())
                    && $membership->getRole() === InstituteRoleEnum::ADMIN
                ) {
                    return true;
                }
            }
        }

        return false;
    }
}
