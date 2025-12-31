<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Member;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Member>
 */
class MemberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Member::class);
    }

    public function findByEmailOrName(
        string $email,
        string $firstname,
        string $lastname,
    ): mixed {
        $qb = $this->createQueryBuilder('m')
            ->where('m.email = :email')
            ->orWhere('m.firstname = :firstname AND m.lastname = :lastname')
            ->setParameter('email', $email)
            ->setParameter('firstname', $firstname)
            ->setParameter('lastname', $lastname);

        return $qb->getQuery()->setMaxResults(1)->getOneOrNullResult();
    }

    public function getByEmail(string $email): mixed
    {
        $qb = $this->createQueryBuilder('m')
            ->where('m.email = :email')
            ->setParameter('email', $email);

        return $qb->getQuery()->setMaxResults(1)->getOneOrNullResult();
    }

    public function countByActiveMember(): mixed
    {
        $qb = $this->createQueryBuilder('m');
        $qb->select($qb->expr()->count('m'))
            ->where('m.isActiveMember = :is')
            ->setParameter('is', true);

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function countByMember(): mixed
    {
        $qb = $this->createQueryBuilder('m');
        $qb->select($qb->expr()->count('m'));

        return $qb->getQuery()->getSingleScalarResult();
    }
}
