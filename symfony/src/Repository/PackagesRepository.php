<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Package;
use App\Entity\WhoCanRentChoice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Package>
 */
class PackagesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Package::class);
    }

    public function getPackageChoicesWithPrivileges(
        ?WhoCanRentChoice $privileges,
    ): mixed {
        $queryBuilder = $this->createQueryBuilder('p')
            ->andWhere('p.rent >= 0.00')
            ->leftJoin('p.whoCanRent', 'r')
            ->andWhere('r = :privilege')
            ->setParameter('privilege', $privileges)
            ->orderBy('p.name', 'ASC');

        return $queryBuilder->getQuery()->getResult();
    }
}
