<?php

namespace App\Repository;

use App\Entity\Item;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Item>
 *
 * @method Item|null find($id, $lockMode = null, $lockVersion = null)
 * @method Item|null findOneBy(array $criteria, array $orderBy = null)
 * @method Item[]    findAll()
 * @method Item[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Item::class);
    }

    public function getAllItemChoices(): mixed
    {
        $queryBuilder = $this->createQueryBuilder('i')
            ->leftJoin('i.packages', 'p')
            ->andWhere('p IS NULL')
            ->orderBy('i.name', 'ASC');
        return $queryBuilder->getQuery()->getResult();
    }
    public function getItemChoicesWithPrivileges(mixed $privileges): mixed
    {
        $queryBuilder = $this->createQueryBuilder('i')
            //->Where('i.cannotBeRented = false')
            ->andWhere('i.rent IS NOT NULL')
            ->andWhere('i.toSpareParts = false')
            ->andWhere('i.forSale = false')
            ->leftJoin('i.packages', 'p')
            ->andWhere('p IS NULL')
            ->leftJoin('i.whoCanRent', 'r')
            ->andWhere('r = :privilege')
            ->setParameter('privilege', $privileges)
            ->orderBy('i.name', 'ASC');
        return $queryBuilder->getQuery()->getResult();
    }
}
