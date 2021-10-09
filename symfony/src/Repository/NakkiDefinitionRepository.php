<?php

namespace App\Repository;

use App\Entity\NakkiDefinition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method NakkiDefinition|null find($id, $lockMode = null, $lockVersion = null)
 * @method NakkiDefinition|null findOneBy(array $criteria, array $orderBy = null)
 * @method NakkiDefinition[]    findAll()
 * @method NakkiDefinition[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NakkiDefinitionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NakkiDefinition::class);
    }

    // /**
    //  * @return NakkiDefinition[] Returns an array of NakkiDefinition objects
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
    public function findOneBySomeField($value): ?NakkiDefinition
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
