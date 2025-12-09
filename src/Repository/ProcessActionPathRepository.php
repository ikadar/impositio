<?php

namespace App\Repository;

use App\Entity\ProcessActionPath;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProcessActionPath>
 */
class ProcessActionPathRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProcessActionPath::class);
    }

    /**
     * Find action path by UUID stored in JSON.
     */
    public function findByUuid(string $uuid): ?ProcessActionPath
    {
        // Use LIKE for SQLite compatibility (JSON_EXTRACT not available in SQLite)
        $results = $this->createQueryBuilder('p')
            ->where('p.json LIKE :pattern')
            ->setParameter('pattern', '%"id":"' . $uuid . '"%')
            ->getQuery()
            ->getResult();

        return $results[0] ?? null;
    }
}
