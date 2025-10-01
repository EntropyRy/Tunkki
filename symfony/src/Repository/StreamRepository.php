<?php

namespace App\Repository;

use App\Entity\Stream;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Stream>
 */
class StreamRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Stream::class);
    }

    /**
     * Persist a single Stream entity.
     */
    public function save(Stream $entity, bool $flush = true): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        if ($flush) {
            $em->flush();
        }
    }

    /**
     * Persist multiple Stream entities (no validation, assumes correct instances).
     *
     * @param iterable<Stream> $streams
     */
    public function saveAll(iterable $streams, bool $flush = true): void
    {
        $em = $this->getEntityManager();
        foreach ($streams as $stream) {
            $em->persist($stream);
        }
        if ($flush) {
            $em->flush();
        }
    }

    /**
     * Stop all currently online streams.
     *
     * For each online stream:
     *  - Sets online=false
     *  - Sets stoppedAt timestamp for any attached StreamArtist without one
     *
     * Returns array of affected streams (now offline). If none were online, returns [].
     *
     * @return Stream[]
     */
    public function stopAllOnline(): array
    {
        $online = $this->findBy(['online' => true]);
        if (!$online) {
            return [];
        }

        $now = new \DateTimeImmutable();
        $em = $this->getEntityManager();

        foreach ($online as $stream) {
            $stream->setOnline(false);
            foreach ($stream->getArtists() as $artist) {
                if (null === $artist->getStoppedAt()) {
                    $artist->setStoppedAt($now);
                }
            }
            $em->persist($stream);
        }

        $em->flush();

        return $online;
    }
}
