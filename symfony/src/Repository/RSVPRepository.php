<?php

namespace App\Repository;

use App\Entity\RSVP;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method RSVP|null find($id, $lockMode = null, $lockVersion = null)
 * @method RSVP|null findOneBy(array $criteria, array $orderBy = null)
 * @method RSVP[]    findAll()
 * @method RSVP[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RSVPRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RSVP::class);
    }

    // /**
    //  * @return RSVP[] Returns an array of RSVP objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('r.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?RSVP
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
