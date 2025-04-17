<?php

namespace App\Repository;

use App\Entity\Member;
use App\Entity\Stream;
use App\Entity\StreamArtist;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StreamArtist>
 */
class StreamArtistRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StreamArtist::class);
    }

    /**
     * Find active stream artists for a particular stream
     */
    public function findActiveArtistsInStream(Stream $stream)
    {
        return $this->createQueryBuilder('sa')
            ->andWhere('sa.stream = :stream')
            ->andWhere('sa.stoppedAt IS NULL')
            ->setParameter('stream', $stream)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find active artist in stream for a specific member
     * Since a member can have multiple artists, we need to check all of them
     */
    public function findActiveMemberArtistInStream(Member $member, Stream $stream)
    {
        // Get all the member's artists IDs
        $artistIds = [];
        foreach ($member->getArtist() as $artist) {
            $artistIds[] = $artist->getId();
        }

        if (empty($artistIds)) {
            return null;
        }

        return $this->createQueryBuilder('sa')
            ->andWhere('sa.stream = :stream')
            ->andWhere('sa.artist IN (:artistIds)')
            ->andWhere('sa.stoppedAt IS NULL')
            ->setParameter('stream', $stream)
            ->setParameter('artistIds', $artistIds)
            ->setMaxResults(1) // Only need one since only one can be active
            ->getQuery()
            ->getOneOrNullResult();
    }
}
