<?php

namespace App\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Session;
use App\Enum\SessionValidationEnum;
use Doctrine\ORM\QueryBuilder;

class SessionPublicExtension implements QueryCollectionExtensionInterface
{
    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if ($resourceClass !== Session::class) {
            return;
        }

        // Ne pas filtrer les sous-ressources (qui ont un uriTemplate avec variables)
        if ($operation && str_contains($operation->getUriTemplate() ?? '', '{instituteId}')) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder
            ->andWhere(sprintf('%s.validation = :open_status', $rootAlias))
            ->setParameter('open_status', SessionValidationEnum::OPEN);
    }
}
