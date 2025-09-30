<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Event;
use App\Entity\Artist;
use App\Entity\EventArtistInfo;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Links the existing fixture Artist to a future published Event via an EventArtistInfo entity
 * so the FrontPage (and other templates/features relying on a non-null "info") can
 * render the artist tile.
 *
 * Design notes:
 * - Depends on ArtistFixtures (provides a canonical Artist with reference ARTIST_REFERENCE)
 *   and EventFixtures (provides a published future Event with reference TEST_EVENT).
 * - Creates a minimal performance slot (StartTime, stage) and a clone of the Artist
 *   similar to the admin prePersist logic (clone used for archival / historical snapshot).
 * - Avoids any conditional method_exists calls per project direction; assumes the used setters exist.
 * - If an EventArtistInfo already exists for the chosen Artist + Event it normalizes / reuses it
 *   instead of creating duplicates (idempotent fixture run).
 */
final class EventArtistInfoFixtures extends Fixture implements DependentFixtureInterface
{
    public const string REFERENCE_EVENT_ARTIST_INFO = 'fixture_event_artist_info';

    public function getDependencies(): array
    {
        return [
            ArtistFixtures::class,
            EventFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        /** @var Artist $artist */
        $artist = $this->getReference(ArtistFixtures::ARTIST_REFERENCE, Artist::class);
        /** @var Event $event */
        $event = $this->getReference(EventFixtures::TEST_EVENT, Event::class);

        // Try to find an existing EventArtistInfo for idempotency
        $existing = $manager->getRepository(EventArtistInfo::class)->findOneBy([
            'Event'  => $event->getId(),
            'Artist' => $artist->getId(),
        ]);

        if ($existing instanceof EventArtistInfo) {
            // Normalize minimal fields (ensure clone present & future-ish start time)
            if (!$existing->getArtistClone() instanceof Artist) {
                $clone = $this->buildArtistClone($artist);
                $existing->setArtistClone($clone);
                $manager->persist($clone);
            }
            if (!$existing->getStartTime() instanceof \DateTimeInterface || $existing->getStartTime() < new \DateTimeImmutable('-1 day')) {
                // Place set ~2h before event start if event date is future; else 2h from now.
                $eventDate = $event->getEventDate();
                if ($eventDate instanceof \DateTimeImmutable && $eventDate > new \DateTimeImmutable('+3 hours')) {
                    $start = $eventDate->sub(new \DateInterval('PT2H'));
                } else {
                    $start = new \DateTimeImmutable('+2 hours');
                }
                $existing->setStartTime($start);
            }
            if ($existing->getStage() === null) {
                $existing->setStage('Main');
            }
            $existing->setSetLength($existing->getSetLength() ?? '60');
            $existing->setAgreeOnRecording($existing->isAgreeOnRecording() ?? true);

            $manager->persist($existing);
            $manager->flush();
            $this->addReference(self::REFERENCE_EVENT_ARTIST_INFO, $existing);
            return;
        }

        // Create new EventArtistInfo
        $info = new EventArtistInfo();
        $info->setEvent($event);
        $info->setArtist($artist);

        // Build & assign clone (archival snapshot)
        $clone = $this->buildArtistClone($artist);
        $info->setArtistClone($clone);

        // Derive a sensible start time: 2 hours before the event if future enough, else now+2h
        $eventDate = $event->getEventDate();
        if ($eventDate instanceof \DateTimeImmutable && $eventDate > new \DateTimeImmutable('+3 hours')) {
            $startTime = $eventDate->sub(new \DateInterval('PT2H'));
        } else {
            $startTime = new \DateTimeImmutable('+2 hours');
        }
        $info->setStartTime($startTime);
        $info->setStage('Main');
        $info->setSetLength('60');
        $info->setAgreeOnRecording(true);
        $info->setFreeWord('Fixture performance slot.');

        $manager->persist($clone);
        $manager->persist($info);
        $manager->flush();

        $this->addReference(self::REFERENCE_EVENT_ARTIST_INFO, $info);
    }

    /**
     * Creates a detached archival clone of an Artist for EventArtistInfo.
     */
    private function buildArtistClone(Artist $artist): Artist
    {
        /** @var Artist $clone */
        $clone = clone $artist;

        // Reset / adjust mutable relations & flags
        // (Assuming these setters exist per project conventions)
        $clone->setMember(null);
        $clone->setCopyForArchive(true);

        // Keep same name/genre/type/picture if available to mirror current data
        // (Direct property copies happen via clone; additional normalization can be added here.)

        return $clone;
    }
}
