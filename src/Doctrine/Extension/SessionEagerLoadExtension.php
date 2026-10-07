<?php

namespace App\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Session;
use Doctrine\ORM\QueryBuilder;

/**
 * Sessions (liste publique et détail) : test, niveau, institut, épreuves, sujets, examinateurs
 * et documents publiés chargés dans la même requête, au lieu d'une série de requêtes par session.
 * Les inscrits du détail sont préchargés à part (SessionItemProvider) pour éviter un produit cartésien.
 */
class SessionEagerLoadExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->eagerLoad($queryBuilder, $queryNameGenerator, $resourceClass);
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        $this->eagerLoad($queryBuilder, $queryNameGenerator, $resourceClass);
    }

    private function eagerLoad(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass): void
    {
        if ($resourceClass !== Session::class) {
            return;
        }

        $root = $queryBuilder->getRootAliases()[0];
        $joins = [
            [$root . '.assessment', 'assessment'],
            [$root . '.level', 'level'],
            [$root . '.institute', 'institute'],
            ['{institute}.stripeAccount', 'stripeAccount'],
            [$root . '.scheduledExams', 'scheduledExam'],
            ['{scheduledExam}.exam', 'exam'],
            ['{scheduledExam}.subject', 'subject'],
            ['{scheduledExam}.examinators', 'examinator'],
            ['{scheduledExam}.examCenter', 'examCenter'],
            [$root . '.documentPublications', 'publication'],
            ['{publication}.documentType', 'documentType'],
        ];

        $aliases = [];
        foreach ($joins as [$path, $name]) {
            $alias = $queryNameGenerator->generateJoinAlias($name);
            $aliases[$name] = $alias;
            $path = preg_replace_callback('/\{(\w+)\}/', static fn ($m) => $aliases[$m[1]], $path);
            $queryBuilder->leftJoin($path, $alias)->addSelect($alias);
        }
    }
}
