<?php

namespace App\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Session;
use App\Enum\SessionStatusEnum;
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
        // Sessions ouvertes dont la date limite n'est pas passée (le verrouillage automatique
        // n'intervient qu'à la lecture d'une session : sans ce filtre, elles resteraient listées)
        $queryBuilder
            ->andWhere(sprintf('%s.status = :open_status', $rootAlias))
            ->andWhere(sprintf('%1$s.limitDateSubscribe IS NULL OR %1$s.limitDateSubscribe > :public_now', $rootAlias))
            ->setParameter('open_status', SessionStatusEnum::OPEN)
            ->setParameter('public_now', new \DateTime());
    }
}
