<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface, UserLoaderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    #[\Override]
    public function upgradePassword(
        PasswordAuthenticatedUserInterface $user,
        string $newEncodedPassword,
    ): void {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newEncodedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    #[\Override]
    public function loadUserByIdentifier(string $usernameOrEmail): ?User
    {
        return $this->getEntityManager()
            ->createQuery(
                'SELECT u
                FROM App\Entity\User u
				INNER JOIN u.member m
                WHERE m.username = :query
                OR m.email = :query',
            )
            ->setParameter('query', $usernameOrEmail)
            ->getOneOrNullResult();
    }

    /**
     * Find a single User by its related Member email (case-insensitive).
     *
     * Returns null if no user has a Member with the provided email.
     */
    public function findOneByMemberEmail(string $email): ?User
    {
        return $this->createQueryBuilder('u')
            ->innerJoin('u.member', 'm')
            ->andWhere('LOWER(m.email) = LOWER(:email)')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // /**
    //  * @return User[] Returns an array of User objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('u.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
