<?php

namespace App\Repository;

use App\Entity\Event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method Event|null find($id, $lockMode = null, $lockVersion = null)
 * @method Event|null findOneBy(array $criteria, array $orderBy = null)
 * @method Event[]    findAll()
 * @method Event[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    /**
     * @return Event[] Returns an array of Event objects
     */
    
    public function getFutureEvents()
    {
        $now = new \DateTime('now-8hours');
        return $this->createQueryBuilder('e')
            ->andWhere('e.EventDate > :date')
            ->andWhere('e.type != :type')
            ->andWhere('e.published = :pub')
            ->setParameter('date', $now)
            ->setParameter('type', 'Announcement')
            ->setParameter('pub', 'true')
            ->orderBy('e.EventDate', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    public function findOneEventByTypeWithSticky($type)
    {
        $e = $this->findOneStickyEventByType($type);
        if (is_null($e)){
            $e = $this->findOneEventByType($type);
        }
        return $e;
    }
    public function findOneEventByType($type)
    {
        return $this->createQueryBuilder('c')
           ->andWhere('c.type = :val')
           ->andWhere('c.published = :pub')
           ->setParameter('val', $type)
           ->setParameter('pub', true)
           ->orderBy('c.EventDate', 'DESC')
           ->setMaxResults(1)
           ->getQuery()
           ->getOneOrNullResult()
           ;
    }
    public function findOneStickyEventByType($type)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.type = :val')
            ->andWhere('r.published = :pub')
            ->andWhere('r.sticky = :sticky')
            ->setParameter('val', $type)
            ->setParameter('pub', true)
            ->setParameter('sticky', 1)
            ->orderBy('r.EventDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
            ;
    }
    public function findEventsByType($type)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.type = :val')
            ->setParameter('val', $type)
            ->orderBy('r.EventDate', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }
    public function findEventsByNotType($type)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.type != :val')
            ->setParameter('val', $type)
            ->orderBy('r.EventDate', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

}
