<?php

namespace App\Repository;

use App\Entity\Booking;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }
    public function findBookingsAtTheSameTime(int $id, $startAt, $endAt): mixed
    {
        $queryBuilder = $this->createQueryBuilder('b')
            ->andWhere('b.retrieval BETWEEN :startAt and :endAt')
            ->orWhere('b.returning BETWEEN :startAt and :endAt')
            ->andWhere('b.itemsReturned = false')
            ->andWhere('b.cancelled = false')
            ->andWhere('b.id != :id')
            ->setParameter('startAt', $startAt)
            ->setParameter('endAt', $endAt)
            ->setParameter('id', $id)
            ->orderBy('b.name', 'ASC');
        return $queryBuilder->getQuery()->getResult();
    }
    public function countHandled(): int
    {
        $qb = $this->createQueryBuilder('b');
        $qb->select($qb->expr()->count('b'))
            ->where('b.cancelled = :is')
            ->setParameter('is', false);
        return $qb->getQuery()->getSingleScalarResult();
    }
}
