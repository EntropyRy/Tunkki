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

    public function findPreviousAndNext(Happening $happening): ?array
    {
        $array = $this->createQueryBuilder('h')
            // ->andWhere('h.time <= :time')
            ->andWhere('h.event = :event')
            ->andWhere('h.releaseThisHappeningInEvent = true')
            // ->setParameter('time', $happening->getTime())
            // ->setParameter('name', $happening->getNameFi())
            ->setParameter('event', $happening->getEvent())
            ->orderBy('h.time', 'ASC')
            ->getQuery()
            ->getResult();
        $key = array_search($happening, $array);
        $lenght = count($array);
        if (0 == $key && $lenght <= 1) {
            return [null, null];
        }
        if (0 == $key && $lenght >= 2) {
            return [null, $array[$key + 1]];
        }
        if ($key + 1 >= $lenght) {
            return [$array[$key - 1], null];
        }

        return [$array[$key - 1], $array[$key + 1]];
    }

    public function findHappeningByEventSlugAndSlug(string $eventSlug, string $slug): ?Happening
    {
        return $this->createQueryBuilder('h')
            ->Where('h.slugFi LIKE :slug')
            ->orWhere('h.slugEn LIKE :slug')
            ->leftJoin('h.event', 'e')
            ->andWhere('e.url = :eventSlug')
            ->setParameter('eventSlug', $eventSlug)
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
