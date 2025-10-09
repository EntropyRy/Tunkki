<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Menu;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Tree\Entity\Repository\NestedTreeRepository;

/**
 * @extends NestedTreeRepository<Menu>
 */
class MenuRepository extends NestedTreeRepository implements ServiceEntityRepositoryInterface
{
    public function __construct(EntityManagerInterface $manager)
    {
        parent::__construct($manager, $manager->getClassMetadata(Menu::class));
    }
    // /**
    //  * @return Menu[] Returns an array of Menu objects
    //  */
    /*
    public function findByExampleField($value)
    {
    return $this->createQueryBuilder('m')
    ->andWhere('m.exampleField = :val')
    ->setParameter('val', $value)
    ->orderBy('m.id', 'ASC')
    ->setMaxResults(10)
    ->getQuery()
    ->getResult()
    ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Menu
    {
    return $this->createQueryBuilder('m')
    ->andWhere('m.exampleField = :val')
    ->setParameter('val', $value)
    ->getQuery()
    ->getOneOrNullResult()
    ;
    }
    */
}
