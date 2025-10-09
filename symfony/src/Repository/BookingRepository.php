<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Booking;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Booking>
 */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    public function findBookingsAtTheSameTime(int $id, $startAt, $endAt): mixed
    {
        // Corrected logical grouping:
        // We want: (retrieval in window OR returning in window) AND itemsReturned = false AND cancelled = false AND b.id != :id
        $qb = $this->createQueryBuilder('b');
        $expr = $qb->expr();

        $qb->andWhere(
            $expr->andX(
                $expr->orX(
                    $expr->between('b.retrieval', ':startAt', ':endAt'),
                    $expr->between('b.returning', ':startAt', ':endAt'),
                ),
                'b.itemsReturned = false',
                'b.cancelled = false',
                'b.id != :id',
            ),
        )
            ->setParameter('startAt', $startAt)
            ->setParameter('endAt', $endAt)
            ->setParameter('id', $id)
            ->orderBy('b.name', 'ASC');

        return $qb->getQuery()->getResult();
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
