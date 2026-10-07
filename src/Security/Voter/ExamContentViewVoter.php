<?php

namespace App\Security\Voter;

use App\Entity\Question;
use App\Entity\Subject;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\PlatformRoleEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Uid\Uuid;

/**
 * Lecture des questions et des sujets : ils contiennent les corrigés, ils sont donc réservés
 * au personnel des instituts concernés (jamais aux candidats).
 * - QUESTION_VIEW : membre actif ADMIN / STAFF / TEACHER d'un institut propriétaire ou acheteur du test ;
 * - SUBJECT_VIEW (Subject ou identifiant) : membre actif ADMIN / STAFF / TEACHER de l'institut de la session.
 */
class ExamContentViewVoter extends Voter
{
    public const QUESTION_VIEW = 'QUESTION_VIEW';
    public const SUBJECT_VIEW = 'SUBJECT_VIEW';

    public const STAFF_ROLES = [InstituteRoleEnum::ADMIN, InstituteRoleEnum::STAFF, InstituteRoleEnum::TEACHER];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return ($attribute === self::QUESTION_VIEW && $subject instanceof Question)
            || ($attribute === self::SUBJECT_VIEW && ($subject instanceof Subject || is_string($subject)));
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if ($user->getPlatformRole() === PlatformRoleEnum::ADMIN) {
            return true;
        }

        if ($subject instanceof Question) {
            foreach ($subject->getAssessment()?->getOwnerships() ?? [] as $ownership) {
                if ($this->isStaffOf($user, $ownership->getInstitute())) {
                    return true;
                }
            }

            return false;
        }

        if (is_string($subject)) {
            $subject = Uuid::isValid($subject) ? $this->entityManager->getRepository(Subject::class)->find($subject) : null;
            if (!$subject) {
                return false;
            }
        }

        return $this->isStaffOf($user, $subject->getScheduledExam()?->getSession()?->getInstitute());
    }

    private function isStaffOf(User $user, ?object $institute): bool
    {
        foreach ($institute?->getMemberships() ?? [] as $membership) {
            if ($membership->isActive()
                && $membership->getUser()?->getId()?->equals($user->getId())
                && in_array($membership->getRole(), self::STAFF_ROLES, true)
            ) {
                return true;
            }
        }

        return false;
    }
}
