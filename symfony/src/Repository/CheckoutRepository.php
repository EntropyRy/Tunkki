<?php

namespace App\Repository;

use App\Entity\Checkout;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Checkout>
 *
 * @method Checkout|null find($id, $lockMode = null, $lockVersion = null)
 * @method Checkout|null findOneBy(array $criteria, array $orderBy = null)
 * @method Checkout[]    findAll()
 * @method Checkout[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CheckoutRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Checkout::class);
    }

    public function add(Checkout $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function save(Checkout $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Checkout $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Checkout[] Returns an array of Checkout objects
     */
    public function findOngoingCheckouts(): ?array
    {
        return $this->createQueryBuilder('c')
            ->select('c')
            ->andWhere('c.status = 0')
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findProductQuantitiesInOngoingCheckouts(): ?array
    {
        $itemsInCheckouts = [];
        $ongoingCheckouts = $this->findOngoingCheckouts();
        foreach ($ongoingCheckouts as $checkout) {
            $cart = $checkout->getCart();
            foreach ($cart->getProducts() as $item) {
                if (!array_key_exists($item->getProduct()->getId(), $itemsInCheckouts)) {
                    $itemsInCheckouts[$item->getProduct()->getId()] = 0;
                }
                $itemsInCheckouts[$item->getProduct()->getId()] += $item->getQuantity();
            }
        }

        return $itemsInCheckouts;
    }

    public function findUnneededCheckouts(): ?array
    {
        return $this->createQueryBuilder('c')
            ->select('c')
            ->andWhere('c.status = -1')
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
    //    public function findOneBySomeField($value): ?Checkout
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
