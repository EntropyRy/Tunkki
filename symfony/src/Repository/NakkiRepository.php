<?php

namespace App\Repository;

use App\Entity\Nakki;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Nakki|null find($id, $lockMode = null, $lockVersion = null)
 * @method Nakki|null findOneBy(array $criteria, array $orderBy = null)
 * @method Nakki[]    findAll()
 * @method Nakki[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NakkiRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Nakki::class);
    }

    // /**
    //  * @return Nakki[] Returns an array of Nakki objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('n.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Nakki
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
