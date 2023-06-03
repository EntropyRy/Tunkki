<?php

namespace App\Repository;

use App\Entity\Happening;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Happening>
 *
 * @method Happening|null find($id, $lockMode = null, $lockVersion = null)
 * @method Happening|null findOneBy(array $criteria, array $orderBy = null)
 * @method Happening[]    findAll()
 * @method Happening[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class HappeningRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Happening::class);
    }

    public function save(Happening $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Happening $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    //    /**
    //     * @return Happening[] Returns an array of Happening objects
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

    public function findHappeningByEventSlugAndSlug($eventSlug, $slug): ?Happening
    {
        return $this->createQueryBuilder('h')
            ->leftJoin('h.event', 'e')
            ->andWhere('e = :eventSlug')
            ->orWhere('h.slugFi LIKE :slug')
            ->orWhere('h.slugEn LIKE :slug')
            ->setParameter('eventSlug', $eventSlug)
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
