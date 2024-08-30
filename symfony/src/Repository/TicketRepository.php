<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\Member;
use App\Entity\Ticket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\Exeption\ORMException;
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

    public function add(Ticket $entity, bool $flush = true): void
    {
        $this->_em->persist($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    public function save(Ticket $entity, bool $flush = true): void
    {
        $this->_em->persist($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    public function remove(Ticket $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    public function findAvailableTicketsCount(Event $event): mixed
    {
        $qb = $this->createQueryBuilder('t');
        $qb->select($qb->expr()->count('t'))
            ->andWhere('t.event = :event')
            ->andWhere('t.status != :status')
            ->setParameter('event', $event)
            ->setParameter('status', 'paid');
        return $qb->getQuery()->getSingleScalarResult();
    }
    public function findAvailableTickets(Event $event): mixed
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.event = :event')
            ->andWhere('t.status = :status')
            ->andWhere('t.owner IS NULL')
            ->setParameter('event', $event)
            ->setParameter('status', 'available')
            ->getQuery()
            ->getResult();
    }
    public function findPresaleTickets(Event $event): mixed
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.event = :event')
            //->andWhere('t.status = :status')
            ->setParameter('event', $event)
            //->setParameter('status', 'available')
            ->orderBy('t.id', 'ASC')
            ->setMaxResults($event->getTicketPresaleCount())
            ->getQuery()
            ->getResult();
    }
    public function findAvailableTicket(Event $event): ?Ticket
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.event = :event')
            ->andWhere('t.status = :status')
            ->andWhere('t.owner IS NULL')
            ->setParameter('event', $event)
            ->setParameter('status', 'available')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
    public function findAvailablePresaleTicket(Event $event): ?Ticket
    {
        $all = $this->findPresaleTickets($event);
        foreach ($all as $ticket) {
            if ($ticket->getStatus() == 'available' && is_null($ticket->getOwner())) {
                return $ticket;
            }
        }
        return null;
    }

    public function findMemberTicketReferenceForEvent(Member $member, Event $event): ?string
    {
        $ticket = $this->createQueryBuilder('t')
            ->select('t.referenceNumber')
            ->andWhere('t.event = :event')
            ->andWhere('t.owner = :member')
            ->setParameter('event', $event)
            ->setParameter('member', $member)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        if ($ticket) {
            return $ticket['referenceNumber'];
        }
        return null;
    }

    public function findMemberTickets(Member $member): mixed
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.owner = :member')
            ->setParameter('member', $member)
            ->getQuery()
            ->getResult();
    }
    public function findTicketsByEmailAndEvent(string $email, Event $event): mixed
    {
        return $this->createQueryBuilder('t')
            ->join('t.owner', 'm')
            ->andWhere('m.email = :email')
            ->orWhere('t.email = :email')
            ->andWhere('t.event = :event')
            ->setParameter('email', $email)
            ->setParameter('event', $event)
            ->getQuery()
            ->getResult();
    }
}
