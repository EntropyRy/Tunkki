<?php

namespace App\Repository;

use App\Entity\DoorLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method DoorLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method DoorLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method DoorLog[]    findAll()
 * @method DoorLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DoorLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DoorLog::class);
    }

    /**
     * @return DoorLog[] Returns an array of DoorLog objects
     */
    public function getLatest(?int $count): mixed
    {
        if (is_null($count)) {
            $count = 10;
        }

        return $this->createQueryBuilder('d')
            ->orderBy('d.createdAt', 'DESC')
            ->setMaxResults($count)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return DoorLog[] Returns an array of DoorLog objects
     */
    public function getSince(?\DateTime $since): mixed
    {
        return $this->createQueryBuilder('d')
            ->where('d.createdAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
    // /**
    //  * @return DoorLog[] Returns an array of DoorLog objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('d.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?DoorLog
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
