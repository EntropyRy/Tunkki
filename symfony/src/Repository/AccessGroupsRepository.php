<?php

namespace App\Repository;

use App\Entity\AccessGroups;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method AccessGroups|null find($id, $lockMode = null, $lockVersion = null)
 * @method AccessGroups|null findOneBy(array $criteria, array $orderBy = null)
 * @method AccessGroups[]    findAll()
 * @method AccessGroups[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AccessGroupsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccessGroups::class);
    }

    // /**
    //  * @return AccessGroups[] Returns an array of AccessGroups objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('a.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?AccessGroups
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
