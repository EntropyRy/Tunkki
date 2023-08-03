<?php

namespace App\Repository;

use App\Entity\Happening;
use App\Entity\HappeningBooking;
use App\Entity\Member;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HappeningBooking>
 *
 * @method HappeningBooking|null find($id, $lockMode = null, $lockVersion = null)
 * @method HappeningBooking|null findOneBy(array $criteria, array $orderBy = null)
 * @method HappeningBooking[]    findAll()
 * @method HappeningBooking[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class HappeningBookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HappeningBooking::class);
    }

    public function save(HappeningBooking $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(HappeningBooking $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    //    /**
    //     * @return HappeningBooking[] Returns an array of HappeningBooking objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('h')
    //            ->andWhere('h.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('h.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    public function findMemberBooking(Member $member, Happening $happening): ?HappeningBooking
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.member = :member')
            ->andWhere('h.happening = :happening')
            ->setParameter('member', $member)
            ->setParameter('happening', $happening)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
