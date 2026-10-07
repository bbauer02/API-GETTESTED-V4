<?php

namespace App\Repository;

use App\Entity\SessionDocumentPublication;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SessionDocumentPublication>
 */
class SessionDocumentPublicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SessionDocumentPublication::class);
    }
}
