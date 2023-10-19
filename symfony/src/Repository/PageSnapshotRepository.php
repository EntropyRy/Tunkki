<?php

namespace App\Repository;

use App\Entity\Sonata\SonataPageSnapshot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PageSnapshotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SonataPageSnapshot::class);
    }

    public function findEnabledByLang(string $lang): mixed
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.publicationDateEnd is null')
            ->andWhere('p.type is not null')
            ->leftJoin('p.site', 's')
            ->andWhere('s.locale = :locale')
            ->setParameter('locale', $lang)
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();
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
