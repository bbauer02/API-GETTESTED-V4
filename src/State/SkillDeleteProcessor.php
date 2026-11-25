<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Assessment;
use App\Entity\Exam;
use App\Entity\Question;
use App\Entity\Skill;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\OwnershipTypeEnum;
use App\Enum\PlatformRoleEnum;
use App\Exception\ConflictHttpException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * DELETE /skills/{id} :
 * - administrateur plateforme, ou ADMIN actif d'un institut propriétaire (OWNER) de chaque test
 *   qui utilise la compétence ;
 * - refusé (409) si la compétence a des sous-compétences ou si une épreuve ou une question l'utilise.
 */
class SkillDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        /** @var Skill $skill */
        $skill = $data;

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Vous devez être connecté.');
        }

        $assessments = $this->entityManager->getRepository(Assessment::class)
            ->createQueryBuilder('a')
            ->innerJoin('a.skills', 's')
            ->where('s.id = :skillId')
            ->setParameter('skillId', $skill->getId(), 'uuid')
            ->getQuery()
            ->getResult();

        if ($user->getPlatformRole() !== PlatformRoleEnum::ADMIN) {
            if ($assessments === []) {
                throw new AccessDeniedHttpException('Vous n\'avez pas les droits pour supprimer cette compétence.');
            }
            foreach ($assessments as $assessment) {
                if (!$this->isOwnerInstituteAdmin($user, $assessment)) {
                    throw new AccessDeniedHttpException('Vous n\'avez pas les droits pour supprimer cette compétence.');
                }
            }
        }

        if (!$skill->getChildren()->isEmpty()) {
            throw new ConflictHttpException('Cette compétence a des sous-compétences : supprimez-les ou déplacez-les d\'abord.');
        }

        $usedByExams = $this->countUsage(Exam::class, $skill);
        $usedByQuestions = $this->countUsage(Question::class, $skill);
        if ($usedByExams > 0 || $usedByQuestions > 0) {
            throw new ConflictHttpException(sprintf(
                'Cette compétence est encore utilisée par %d épreuve(s) et %d question(s) : elle ne peut pas être supprimée.',
                $usedByExams,
                $usedByQuestions
            ));
        }

        foreach ($assessments as $assessment) {
            $assessment->removeSkill($skill);
        }

        $this->entityManager->remove($skill);
        $this->entityManager->flush();

        return null;
    }

    private function countUsage(string $class, Skill $skill): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from($class, 'e')
            ->innerJoin('e.skills', 's')
            ->where('s.id = :skillId')
            ->setParameter('skillId', $skill->getId(), 'uuid')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function isOwnerInstituteAdmin(User $user, Assessment $assessment): bool
    {
        foreach ($assessment->getOwnerships() as $ownership) {
            if ($ownership->getOwnershipType() !== OwnershipTypeEnum::OWNER || $ownership->getInstitute() === null) {
                continue;
            }

            foreach ($ownership->getInstitute()->getMemberships() as $membership) {
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
