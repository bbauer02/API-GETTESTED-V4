<?php

namespace App\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\AssessmentOwnership;
use App\Entity\InstituteMembership;
use App\Entity\Question;
use App\Entity\User;
use App\Enum\MembershipStatusEnum;
use App\Enum\PlatformRoleEnum;
use App\Security\Voter\ExamContentViewVoter;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * La banque de questions (avec corrigés) n'est listée qu'au personnel des instituts
 * propriétaires ou acheteurs du test ; un candidat obtient une liste vide.
 */
class QuestionAccessExtension implements QueryCollectionExtensionInterface
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (!is_a($resourceClass, Question::class, true)) {
            return;
        }

        $user = $this->security->getUser();
        if ($user instanceof User && $user->getPlatformRole() === PlatformRoleEnum::ADMIN) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $ownership = $queryNameGenerator->generateJoinAlias('ownership');
        $membership = $queryNameGenerator->generateJoinAlias('membership');
        $userParam = $queryNameGenerator->generateParameterName('question_viewer');
        $rolesParam = $queryNameGenerator->generateParameterName('question_roles');
        $activeParam = $queryNameGenerator->generateParameterName('question_active');

        $accessible = $queryBuilder->getEntityManager()->createQueryBuilder()
            ->select('1')
            ->from(AssessmentOwnership::class, $ownership)
            ->join(InstituteMembership::class, $membership, 'WITH', sprintf('%s.institute = %s.institute', $membership, $ownership))
            ->where(sprintf('%s.assessment = %s.assessment', $ownership, $rootAlias))
            ->andWhere(sprintf('%s.user = :%s', $membership, $userParam))
            ->andWhere(sprintf('%s.status = :%s', $membership, $activeParam))
            ->andWhere(sprintf('%s.role IN (:%s)', $membership, $rolesParam));

        $queryBuilder
            ->andWhere($queryBuilder->expr()->exists($accessible->getDQL()))
            ->setParameter($userParam, $user instanceof User ? $user->getId() : null, 'uuid')
            ->setParameter($rolesParam, array_map(static fn ($role) => $role->value, ExamContentViewVoter::STAFF_ROLES))
            ->setParameter($activeParam, MembershipStatusEnum::ACTIVE->value);
    }
}
