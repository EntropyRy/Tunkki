<?php

namespace App\Repository;

use App\Entity\Event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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

    public function getSitemapEvents(): mixed
    {
        $now = new \DateTime();
        return $this->createQueryBuilder('e')
            ->andWhere('e.publishDate <= :now')
            ->andWhere('e.published = :pub')
            ->andWhere('e.externalUrl = :ext')
            ->setParameter('now', $now)
            ->setParameter('pub', true)
            ->setParameter('ext', false)
            ->orderBy('e.EventDate', 'DESC')
            ->getQuery()
            ->getResult();
    }
    public function getRSSEvents(): mixed
    {
        $now = new \DateTime();
        return $this->createQueryBuilder('e')
            ->andWhere('e.publishDate <= :now')
            ->andWhere('e.published = :pub')
            ->setParameter('now', $now)
            ->setParameter('pub', true)
            ->orderBy('e.EventDate', 'DESC')
            ->getQuery()
            ->getResult();
    }
    public function getFutureEvents(): mixed
    {
        $now = new \DateTime();
        $end = new \DateTime();
        $future =  $this->createQueryBuilder('e')
            ->andWhere('e.publishDate <= :now')
            ->andWhere('e.EventDate > :date')
            ->andWhere('e.type != :type')
            ->andWhere('e.published = :pub')
            ->setParameter('now', $now)
            ->setParameter('date', $end->modify('-30 hours'))
            ->setParameter('type', 'Announcement')
            ->setParameter('pub', true)
            ->orderBy('e.EventDate', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
        return $future;
    }
    public function getUnpublishedFutureEvents(): mixed
    {
        $now = new \DateTime();
        $end = new \DateTime();
        $future =  $this->createQueryBuilder('e')
            ->andWhere('e.publishDate <= :now')
            ->andWhere('e.EventDate > :date')
            ->andWhere('e.type != :type')
            ->andWhere('e.published = :pub')
            ->setParameter('now', $now)
            ->setParameter('date', $end->modify('-30 hours'))
            ->setParameter('type', 'Announcement')
            ->setParameter('pub', false)
            ->orderBy('e.EventDate', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
        return $future;
    }
    public function findOneEventByType(string $type): ?Event
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.type = :val')
            ->andWhere('c.published = :pub')
            ->setParameter('val', $type)
            ->setParameter('pub', true)
            ->orderBy('c.EventDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
    public function findEventBySlugAndYear(string $slug, int $year): mixed
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.url = :val')
            ->andWhere('YEAR(r.EventDate) = :year')
            ->setParameter('val', $slug)
            ->setParameter('year', $year)
            ->orderBy('r.EventDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
    public function findEventsByType(string $type): mixed
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.type = :val')
            ->setParameter('val', $type)
            ->orderBy('r.EventDate', 'DESC')
            ->getQuery()
            ->getResult();
    }
    public function findCalendarEvents(): mixed
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.externalUrl = :ext')
            ->setParameter('ext', false)
            ->orderBy('e.EventDate', 'DESC')
            ->getQuery()
            ->getResult();
    }
    public function findPublicEventsByNotType(string $type): mixed
    {
        $now = new \DateTime();
        return $this->createQueryBuilder('r')
            ->andWhere('r.type != :val')
            ->andWhere('r.published = :pub')
            ->andWhere('r.publishDate <= :pubDate')
            ->setParameter('pub', true)
            ->setParameter('pubDate', $now)
            ->setParameter('val', $type)
            ->orderBy('r.EventDate', 'DESC')
            ->getQuery()
            ->getResult();
    }
    public function countDone(): mixed
    {
        $qb = $this->createQueryBuilder('b');
        $qb->select($qb->expr()->count('b'))
            ->where('b.cancelled = :is')
            ->andWhere('b.type != :val')
            ->setParameter('val', 'announcement')
            ->setParameter('is', false);
        return $qb->getQuery()->getSingleScalarResult();
    }
}
