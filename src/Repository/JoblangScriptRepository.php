<?php

namespace App\Repository;

use App\Entity\JoblangScript;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JoblangScript>
 */
class JoblangScriptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JoblangScript::class);
    }

        public function findWithParts($id): ?JoblangScript
        {
            $joblangScript = $this->createQueryBuilder('script')
                ->leftJoin('script.lines', 'lines')
                ->leftJoin('lines.job', 'jobs')
                ->leftJoin('jobs.parts', 'parts')
                ->addSelect('lines')
                ->addSelect('jobs')
                ->addSelect('parts')
                ->where('script.id = :id')
                ->setParameter('id', $id)
                ->getQuery()
                ->getOneOrNullResult()
            ;

            return $joblangScript;

        }

}
