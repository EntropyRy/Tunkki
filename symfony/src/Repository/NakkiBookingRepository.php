<?php

namespace App\Repository;

use App\Entity\NakkiBooking;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method NakkiBooking|null find($id, $lockMode = null, $lockVersion = null)
 * @method NakkiBooking|null findOneBy(array $criteria, array $orderBy = null)
 * @method NakkiBooking[]    findAll()
 * @method NakkiBooking[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NakkiBookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NakkiBooking::class);
    }

    /**
     * @return NakkiBooking[] Returns an array of NakkiBooking objects
     */
    
    public function findMemberEventBookings($member, $event)
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.event = :event')
            ->andWhere('n.member = :member')
            ->setParameter('event', $event)
            ->setParameter('member', $member)
            ->orderBy('n.startAt', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    public function findMemberEventBookingsAtSameTime($member, $event, $start, $end)
    {
        return $this->createQueryBuilder('n')
            //->where('n.startAt BETWEEN :start and :end OR n.endAt BETWEEN :start and :end' )
            ->where('n.startAt = :start OR n.endAt = :end')
            ->orWhere ('n.startAt BETWEEN :startMod and :endMod OR n.endAt BETWEEN :startMod and :endMod')
            ->andWhere('n.event = :event')
            ->andWhere('n.member = :member')
            ->setParameter('event', $event)
            ->setParameter('member', $member)
            ->setParameter('startMod', $start->modify('+1 minute')->format('Y-m-d H:i:s'))
            ->setParameter('endMod', $end->modify('-1 minute')->format('Y-m-d H:i:s'))
            ->setParameter('start', $start->format('Y-m-d H:i:s'))
            ->setParameter('end', $end->format('Y-m-d H:i:s'))
            ->orderBy('n.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    

    /*
    public function findOneBySomeField($value): ?NakkiBooking
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
