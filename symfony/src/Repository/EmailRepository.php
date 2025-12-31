<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Email;
use App\Enum\EmailPurpose;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Email>
 */
class EmailRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Email::class);
    }

    /**
     * @return array<EmailPurpose>
     */
    public function findExistingSingletonPurposes(?Email $exclude = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->select('e.purpose')
            ->where('e.purpose IN (:singletons)')
            ->setParameter('singletons', EmailPurpose::singletons());

        if ($exclude?->getId()) {
            $qb->andWhere('e.id != :currentId')
                ->setParameter('currentId', $exclude->getId());
        }

        $results = $qb->getQuery()->getResult();

        return array_column($results, 'purpose');
    }
    // /**
    //  * @return Email[] Returns an array of Email objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('e.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Email
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
