<?php

namespace App\Repository;

use App\Entity\SubjectCompositionRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SubjectCompositionRule>
 */
class SubjectCompositionRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SubjectCompositionRule::class);
    }
}
