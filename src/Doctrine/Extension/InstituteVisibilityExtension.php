<?php

namespace App\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Institute;
use App\Entity\InstituteMembership;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\InstituteStatusEnum;
use App\Enum\MembershipStatusEnum;
use App\Enum\PlatformRoleEnum;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Instituts visibles : l'administrateur plateforme voit tout ; les autres ne voient que les instituts
 * validés (ACTIVE) et ceux dont ils sont membres actifs (hors candidat), quel que soit leur statut
 * (un institut en attente de validation reste accessible à son équipe).
 */
class InstituteVisibilityExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $this->restrict($queryBuilder, $queryNameGenerator, $resourceClass);
    }

    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $this->restrict($queryBuilder, $queryNameGenerator, $resourceClass);
    }

    private function restrict(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass): void
    {
        if ($resourceClass !== Institute::class) {
            return;
        }

        $user = $this->security->getUser();
        if ($user instanceof User && $user->getPlatformRole() === PlatformRoleEnum::ADMIN) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $statusParam = $queryNameGenerator->generateParameterName('visible_status');

        if (!$user instanceof User) {
            $queryBuilder
                ->andWhere(sprintf('%s.status = :%s', $rootAlias, $statusParam))
                ->setParameter($statusParam, InstituteStatusEnum::ACTIVE);

            return;
        }

        $membershipAlias = $queryNameGenerator->generateJoinAlias('visible_membership');
        $userParam = $queryNameGenerator->generateParameterName('visible_user');
        $membershipStatusParam = $queryNameGenerator->generateParameterName('visible_membership_status');
        $customerParam = $queryNameGenerator->generateParameterName('visible_customer');

        $memberSubQuery = sprintf(
            'SELECT 1 FROM %s %s WHERE %2$s.institute = %s AND %2$s.user = :%s AND %2$s.status = :%s AND %2$s.role <> :%s',
            InstituteMembership::class,
            $membershipAlias,
            $rootAlias,
            $userParam,
            $membershipStatusParam,
            $customerParam,
        );

        $queryBuilder
            ->andWhere(sprintf('%s.status = :%s OR EXISTS(%s)', $rootAlias, $statusParam, $memberSubQuery))
            ->setParameter($statusParam, InstituteStatusEnum::ACTIVE)
            ->setParameter($userParam, $user->getId(), 'uuid')
            ->setParameter($membershipStatusParam, MembershipStatusEnum::ACTIVE)
            ->setParameter($customerParam, InstituteRoleEnum::CUSTOMER);
    }
}
