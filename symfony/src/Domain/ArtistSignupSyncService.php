<?php

declare(strict_types=1);

namespace App\Domain;

use App\Entity\Artist;
use App\Entity\EventArtistInfo;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Handles syncing Artist profile data to EventArtistInfo clones.
 *
 * When an artist edits their profile, active event signups can have their
 * artistClone updated to reflect the latest profile information.
 *
 * A signup is "active" (syncable) until the artist's slot start time.
 * If no slot time is set, falls back to event date.
 */
final readonly class ArtistSignupSyncService
{
    public function __construct(
        private EventTemporalStateService $temporalService,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Find all active EventArtistInfo records for an artist.
     *
     * "Active" means the slot hasn't started yet.
     * Uses StartTime if set, otherwise falls back to event date.
     *
     * @return array<EventArtistInfo>
     */
    public function findActiveSignups(Artist $artist): array
    {
        $active = [];

        foreach ($artist->getEventArtistInfos() as $info) {
            $event = $info->getEvent();
            if (null === $event) {
                continue;
            }

            // Use slot start time if available, otherwise check event
            $slotStart = $info->getStartTime();
            if (null !== $slotStart) {
                // Slot has a scheduled time - check if it's in the future
                if (!$this->temporalService->isDateTimeInPast($slotStart)) {
                    $active[] = $info;
                }
            } elseif (!$this->temporalService->isInPast($event)) {
                // No slot time - fall back to event date check
                $active[] = $info;
            }
        }

        return $active;
    }

    /**
     * Sync artist profile data to the artistClone of an EventArtistInfo.
     *
     * Copies: name, genre, type, hardware, bio, bioEn, picture, links
     */
    public function syncToClone(EventArtistInfo $info): bool
    {
        $artist = $info->getArtist();
        $clone = $info->getArtistClone();

        if (!$artist instanceof Artist || !$clone instanceof Artist) {
            return false;
        }

        $clone->setName($artist->getName());
        $clone->setGenre($artist->getGenre());
        $clone->setType($artist->getType());
        $clone->setHardware($artist->getHardware());
        $clone->setBio($artist->getBio());
        $clone->setBioEn($artist->getBioEn());
        $clone->setPicture($artist->getPicture());
        $clone->setLinks($artist->getLinks());

        return true;
    }

    /**
     * Sync artist profile data to multiple EventArtistInfo clones.
     *
     * @param array<EventArtistInfo> $signups
     *
     * @return int Number of signups successfully synced
     */
    public function syncMultiple(array $signups): int
    {
        $synced = 0;

        foreach ($signups as $info) {
            if ($this->syncToClone($info)) {
                ++$synced;
            }
        }

        if ($synced > 0) {
            $this->em->flush();
        }

        return $synced;
    }

    /**
     * Find signups by their IDs that belong to a specific artist.
     *
     * @param array<int> $ids
     *
     * @return array<EventArtistInfo>
     */
    public function findSignupsByIds(Artist $artist, array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $found = [];

        foreach ($artist->getEventArtistInfos() as $info) {
            if (\in_array($info->getId(), $ids, true)) {
                $found[] = $info;
            }
        }

        return $found;
    }
}
