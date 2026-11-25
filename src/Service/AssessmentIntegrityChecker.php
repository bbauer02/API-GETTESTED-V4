<?php

namespace App\Service;

use App\Entity\Assessment;
use App\Entity\Exam;
use App\Entity\Institute;
use App\Entity\Question;
use App\Entity\Session;
use App\Entity\User;
use App\Enum\OwnershipTypeEnum;
use App\Enum\PlatformRoleEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use App\Exception\ConflictHttpException;

/**
 * Contrôles d'intégrité d'un test (Assessment) à la création et à la modification :
 * - test parent autorisé (interne, ou lié à un institut propriétaire du test) et sans cycle ;
 * - niveaux encore utilisés par des épreuves, questions ou sessions non retirés.
 */
class AssessmentIntegrityChecker
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param Institute[] $ownerInstitutes instituts propriétaires du test (ou institut créateur)
     */
    public function assertParentAllowed(Assessment $assessment, ?User $user, array $ownerInstitutes): void
    {
        $parent = $assessment->getParent();
        if ($parent === null) {
            return;
        }

        // Pas de cycle (le test ne peut pas être son propre ancêtre)
        $cursor = $parent;
        $guard = 0;
        while ($cursor !== null && $guard++ < 50) {
            if ($cursor === $assessment
                || ($assessment->getId() !== null && $cursor->getId()?->equals($assessment->getId()))
            ) {
                throw new UnprocessableEntityHttpException('Un test ne peut pas être son propre test parent.');
            }
            $cursor = $cursor->getParent();
        }

        if ($user instanceof User && $user->getPlatformRole() === PlatformRoleEnum::ADMIN) {
            return;
        }

        if ($parent->isInternal()) {
            return;
        }

        foreach ($parent->getOwnerships() as $ownership) {
            $institute = $ownership->getInstitute();
            if ($institute === null) {
                continue;
            }
            foreach ($ownerInstitutes as $ownerInstitute) {
                if ($institute->getId()?->equals($ownerInstitute->getId())) {
                    return;
                }
            }
        }

        throw new AccessDeniedHttpException('Ce test parent n\'est pas disponible pour votre institut.');
    }

    /**
     * @return Institute[]
     */
    public function getOwnerInstitutes(Assessment $assessment): array
    {
        $institutes = [];
        foreach ($assessment->getOwnerships() as $ownership) {
            if ($ownership->getOwnershipType() === OwnershipTypeEnum::OWNER && $ownership->getInstitute() !== null) {
                $institutes[] = $ownership->getInstitute();
            }
        }

        return $institutes;
    }

    /**
     * Refuse la modification si une épreuve, une question ou une session du test (ou d'un test enfant)
     * pointe vers un niveau qui ne serait plus disponible (niveau retiré du test ou parent changé).
     */
    public function assertLevelsStillAvailable(Assessment $assessment): void
    {
        if ($assessment->getId() === null) {
            return;
        }

        $ownIds = $this->levelIds($assessment);
        $parentIds = $assessment->getParent() ? $this->levelIds($assessment->getParent()) : [];

        $missing = $this->countOrphans($assessment, array_merge($ownIds, $parentIds));

        foreach ($assessment->getChildren() as $child) {
            $missing += $this->countOrphans($child, array_merge($this->levelIds($child), $ownIds));
        }

        if ($missing > 0) {
            throw new ConflictHttpException(sprintf(
                'Ce changement retirerait des niveaux encore utilisés par %d épreuve(s), question(s) ou session(s). Modifiez ces éléments avant de supprimer le niveau.',
                $missing
            ));
        }
    }

    /** @return string[] */
    private function levelIds(Assessment $assessment): array
    {
        $ids = [];
        foreach ($assessment->getLevels() as $level) {
            if ($level->getId() !== null) {
                $ids[] = $level->getId()->toRfc4122();
            }
        }

        return $ids;
    }

    /**
     * @param string[] $allowedLevelIds
     */
    private function countOrphans(Assessment $assessment, array $allowedLevelIds): int
    {
        $count = 0;
        foreach ([Exam::class, Question::class, Session::class] as $class) {
            $qb = $this->entityManager->createQueryBuilder()
                ->select('COUNT(e.id)')
                ->from($class, 'e')
                ->where('e.assessment = :assessment')
                ->andWhere('e.level IS NOT NULL')
                ->setParameter('assessment', $assessment->getId(), 'uuid');

            if ($allowedLevelIds !== []) {
                $qb->andWhere('e.level NOT IN (:allowed)')
                    ->setParameter('allowed', $allowedLevelIds);
            }

            $count += (int) $qb->getQuery()->getSingleScalarResult();
        }

        return $count;
    }
}
