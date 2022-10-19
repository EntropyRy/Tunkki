<?php

namespace App\Repository;

use App\Entity\EventArtistInfo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method EventArtistInfo|null find($id, $lockMode = null, $lockVersion = null)
 * @method EventArtistInfo|null findOneBy(array $criteria, array $orderBy = null)
 * @method EventArtistInfo[]    findAll()
 * @method EventArtistInfo[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EventArtistInfoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventArtistInfo::class);
    }

    // /**
    //  * @return EventArtistInfo[] Returns an array of EventArtistInfo objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('e.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?EventArtistInfo
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
    public function findOnePublicEventArtistInfo(): ?EventArtistInfo
    {
        $infos = $this->createQueryBuilder('i')
            ->leftJoin('i.Event', 'e')
            ->where('e.published = :bool')
            ->andWhere('i.artistClone IS NOT NULL')
            ->andWhere('i.StartTime IS NOT NULL')
            ->setParameter('bool', true)
            ->getQuery()
            ->getResult()
        ;
        shuffle($infos);
        return array_pop($infos);
    }
}
