<?php

namespace App\Repository;

use App\Entity\Ticket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ticket>
 *
 * @method Ticket|null find($id, $lockMode = null, $lockVersion = null)
 * @method Ticket|null findOneBy(array $criteria, array $orderBy = null)
 * @method Ticket[]    findAll()
 * @method Ticket[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ticket::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(Ticket $entity, bool $flush = true): void
    {
        $this->_em->persist($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function remove(Ticket $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    // /**
    //  * @return Ticket[] Returns an array of Ticket objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('t.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Ticket
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
    public function findAvailableTicketsCount($event)
    {
        $qb = $this->createQueryBuilder('t');
        $qb->select($qb->expr()->count('t'))
            ->andWhere('t.event = :event')
            ->andWhere('t.status != :status')
            ->setParameter('event', $event)
            ->setParameter('status', 'paid');
        return $qb->getQuery()->getSingleScalarResult();
    }
    public function findAvailableTickets($event)
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.event = :event')
            ->andWhere('t.status = :status')
            ->andWhere('t.owner IS NULL')
            ->setParameter('event', $event)
            ->setParameter('status', 'available')
            ->getQuery()
            ->getResult()
        ;
    }
    public function findPresaleTickets($event)
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.event = :event')
            //->andWhere('t.status = :status')
            ->setParameter('event', $event)
            //->setParameter('status', 'available')
            ->orderBy('t.id', 'ASC')
            ->setMaxResults($event->getTicketPresaleCount())
            ->getQuery()
            ->getResult()
        ;
    }
    public function findAvailableTicket($event): ?Ticket
    {

        return $this->createQueryBuilder('t')
            ->andWhere('t.event = :event')
            ->andWhere('t.status = :status')
            ->andWhere('t.owner IS NULL')
            ->setParameter('event', $event)
            ->setParameter('status', 'available')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    public function findAvailablePresaleTicket($event): ?Ticket
    {
        $all = $this->findPresaleTickets($event);
        foreach ($all as $ticket){
            if ($ticket->getStatus() == 'available' && is_null($ticket->getOwner())){
                return $ticket;
            }
        }
        return null;
    }
}
