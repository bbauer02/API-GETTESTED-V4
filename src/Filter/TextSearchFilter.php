<?php

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

/**
 * Recherche texte « q » sur plusieurs champs à la fois (OU), insensible à la casse.
 *
 *   #[ApiFilter(TextSearchFilter::class, properties: ['firstname', 'lastname', 'email'])]
 *   GET /api/users?q=dupont
 *
 * Les champs d'objets embarqués s'écrivent avec un point (ex. 'buyer.name').
 */
class TextSearchFilter extends AbstractFilter
{
    public const PARAMETER = 'q';

    protected function filterProperty(
        string $property,
        mixed $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        // Le filtre est porté par un seul paramètre « q », traité une fois pour tous les champs
        if ($property !== self::PARAMETER || !is_string($value) || trim($value) === '') {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $parameter = $queryNameGenerator->generateParameterName('q');

        $conditions = [];
        foreach (array_keys($this->getProperties() ?? []) as $field) {
            $conditions[] = sprintf('LOWER(%s.%s) LIKE :%s', $alias, $field, $parameter);
        }

        if ($conditions) {
            $queryBuilder
                ->andWhere('(' . implode(' OR ', $conditions) . ')')
                ->setParameter($parameter, '%' . mb_strtolower(trim($value)) . '%');
        }
    }

    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $value = $context['filters'][self::PARAMETER] ?? null;
        $this->filterProperty(self::PARAMETER, $value, $queryBuilder, $queryNameGenerator, $resourceClass, $operation, $context);
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            self::PARAMETER => [
                'property' => null,
                'type' => 'string',
                'required' => false,
                'description' => 'Recherche texte sur : ' . implode(', ', array_keys($this->getProperties() ?? [])),
            ],
        ];
    }
}
