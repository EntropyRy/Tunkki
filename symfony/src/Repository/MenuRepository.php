<?php

namespace App\Repository;

use App\Entity\Menu;
use Gedmo\Tree\Entity\Repository\NestedTreeRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Menu|null find($id, $lockMode = null, $lockVersion = null)
 * @method Menu|null findOneBy(array $criteria, array $orderBy = null)
 * @method Menu[]    findAll()
 * @method Menu[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MenuRepository extends NestedTreeRepository
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
