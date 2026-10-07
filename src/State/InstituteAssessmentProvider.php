<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Assessment;
use App\Entity\Institute;
use App\Entity\User;
use App\Enum\PlatformRoleEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns assessments available to an institute:
 * - Internal assessments (available to all)
 * - Assessments owned by the institute (OWNER)
 * - Assessments bought by the institute (BUYER)
 */
class InstituteAssessmentProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $instituteId = $uriVariables['instituteId'] ?? null;
        $institute = $this->entityManager->getRepository(Institute::class)->find($instituteId);

        if (!$institute) {
            throw new NotFoundHttpException('Institut introuvable.');
        }

        $currentUser = $this->security->getUser();

        if (!$currentUser instanceof User || !$this->isMember($currentUser, $institute)) {
            throw new AccessDeniedHttpException('Vous n\'avez pas les droits pour voir les assessments de cet institut.');
        }

        // Internal assessments (available to all institutes)
        $internalAssessments = $this->entityManager->getRepository(Assessment::class)->findBy([
            'isInternal' => true,
        ]);

        // Assessments linked to this institute via ownership (OWNER or BUYER)
        $qb = $this->entityManager->createQueryBuilder();
        $instituteAssessments = $qb
            ->select('a')
            ->from(Assessment::class, 'a')
            ->innerJoin('a.ownerships', 'o')
            ->where('o.institute = :institute')
            ->andWhere('a.isInternal = false')
            ->setParameter('institute', $institute)
            ->getQuery()
            ->getResult();

        // Merge and deduplicate
        $merged = [];
        foreach ($internalAssessments as $assessment) {
            $merged[$assessment->getId()->toRfc4122()] = $assessment;
        }
        foreach ($instituteAssessments as $assessment) {
            $merged[$assessment->getId()->toRfc4122()] = $assessment;
        }

        return array_values($merged);
    }

    private function isMember(User $user, Institute $institute): bool
    {
        if ($user->getPlatformRole() === PlatformRoleEnum::ADMIN) {
            return true;
        }

        foreach ($institute->getMemberships() as $membership) {
            if ($membership->getUser()?->getId()?->equals($user->getId())) {
                return true;
            }
        }

        return false;
    }
}
