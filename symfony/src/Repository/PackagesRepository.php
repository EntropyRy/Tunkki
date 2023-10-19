<?php

namespace App\Repository;

use App\Entity\Package;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PackagesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Package::class);
    }
    public function getPackageChoicesWithPrivileges($privileges): mixed
    {
        $queryBuilder = $this->createQueryBuilder('p')
                   ->andWhere('p.rent >= 0.00')
                   ->leftJoin('p.whoCanRent', 'r')
                   ->andWhere('r = :privilege')
                   ->setParameter('privilege', $privileges)
                   ->orderBy('p.name', 'ASC');
        return $queryBuilder->getQuery()->getResult();
    }
}
