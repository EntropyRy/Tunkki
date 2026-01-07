<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Domain\ArtistSignupSyncService;
use App\Domain\EventTemporalStateService;
use App\Entity\Artist;
use App\Entity\EventArtistInfo;
use App\Time\ClockInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ArtistSignupSyncService pure logic methods.
 *
 * Tests cover:
 *  - Syncing artist data to clone (all fields)
 *  - Edge cases (null artist, null clone)
 *  - Finding signups by IDs with artist ownership validation
 *
 * Note: findActiveSignups requires EventTemporalStateService which is final
 * and tested via functional tests instead.
 */
final class ArtistSignupSyncServiceTest extends TestCase
{
    private ArtistSignupSyncService $service;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2025-06-15 12:00:00');

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($this->now);

        $temporalService = new EventTemporalStateService($clock);
        $em = $this->createStub(EntityManagerInterface::class);
        $this->service = new ArtistSignupSyncService($temporalService, $em);
    }

    public function testSyncToCloneCopiesAllFields(): void
    {
        $artist = new Artist();
        $artist->setName('Updated Name');
        $artist->setGenre('House');
        $artist->setType('Live');
        $artist->setHardware('Modular Synth');
        $artist->setBio('New Finnish bio');
        $artist->setBioEn('New English bio');
        $artist->setLinks(['https://soundcloud.com/test']);

        $clone = new Artist();
        $clone->setName('Original Name');
        $clone->setGenre('Techno');
        $clone->setType('DJ');
        $clone->setHardware('CDJs');
        $clone->setBio('Old Finnish bio');
        $clone->setBioEn('Old English bio');
        $clone->setLinks([]);

        $signup = new EventArtistInfo();
        $signup->setArtist($artist);
        $signup->setArtistClone($clone);

        $result = $this->service->syncToClone($signup);

        $this->assertTrue($result);
        $this->assertSame('Updated Name', $clone->getName());
        $this->assertSame('House', $clone->getGenre());
        $this->assertSame('Live', $clone->getType());
        $this->assertSame('Modular Synth', $clone->getHardware());
        $this->assertSame('New Finnish bio', $clone->getBio());
        $this->assertSame('New English bio', $clone->getBioEn());
        $this->assertSame(['https://soundcloud.com/test'], $clone->getLinks());
    }

    public function testSyncToCloneReturnsFalseWhenArtistIsNull(): void
    {
        $signup = new EventArtistInfo();
        $signup->setArtistClone(new Artist());

        $result = $this->service->syncToClone($signup);

        $this->assertFalse($result);
    }

    public function testSyncToCloneReturnsFalseWhenCloneIsNull(): void
    {
        $artist = new Artist();
        $signup = new EventArtistInfo();
        $signup->setArtist($artist);

        $result = $this->service->syncToClone($signup);

        $this->assertFalse($result);
    }

    public function testFindActiveSignupsSkipsSignupsWithNullEvent(): void
    {
        $artist = new Artist();

        // Create signup with null event
        $signupNoEvent = new EventArtistInfo();
        // Event is null by default

        $signups = new ArrayCollection([$signupNoEvent]);

        $reflection = new \ReflectionClass($artist);
        $property = $reflection->getProperty('eventArtistInfos');
        $property->setValue($artist, $signups);

        $active = $this->service->findActiveSignups($artist);

        $this->assertSame([], $active);
    }

    public function testFindActiveSignupsExcludesPastEventSignups(): void
    {
        $artist = new Artist();

        // Create a past event (clock is set to 2025-06-15)
        $pastEvent = new \App\Entity\Event();
        $pastEvent->setEventDate(new \DateTimeImmutable('2025-01-01 20:00:00'));

        $signup = new EventArtistInfo();
        $signup->setEvent($pastEvent);

        $signups = new ArrayCollection([$signup]);

        $reflection = new \ReflectionClass($artist);
        $property = $reflection->getProperty('eventArtistInfos');
        $property->setValue($artist, $signups);

        $active = $this->service->findActiveSignups($artist);

        $this->assertSame([], $active);
    }

    public function testFindActiveSignupsUsesSlotStartTimeWhenSet(): void
    {
        $artist = new Artist();

        // Create a future event
        $futureEvent = new \App\Entity\Event();
        $futureEvent->setEventDate(new \DateTimeImmutable('2025-07-01 20:00:00'));

        // Signup with slot time in the future (clock is set to 2025-06-15 12:00)
        $signup = new EventArtistInfo();
        $signup->setEvent($futureEvent);
        $signup->setStartTime(new \DateTimeImmutable('2025-07-01 22:00:00'));

        $signups = new ArrayCollection([$signup]);

        $reflection = new \ReflectionClass($artist);
        $property = $reflection->getProperty('eventArtistInfos');
        $property->setValue($artist, $signups);

        $active = $this->service->findActiveSignups($artist);

        $this->assertCount(1, $active);
        $this->assertSame($signup, $active[0]);
    }

    public function testFindActiveSignupsExcludesSignupsWhereSlotHasStarted(): void
    {
        $artist = new Artist();

        // Create a future event (event not in past)
        $futureEvent = new \App\Entity\Event();
        $futureEvent->setEventDate(new \DateTimeImmutable('2025-06-15 18:00:00'));

        // Signup with slot time in the past (clock is set to 2025-06-15 12:00)
        $signup = new EventArtistInfo();
        $signup->setEvent($futureEvent);
        $signup->setStartTime(new \DateTimeImmutable('2025-06-15 10:00:00'));

        $signups = new ArrayCollection([$signup]);

        $reflection = new \ReflectionClass($artist);
        $property = $reflection->getProperty('eventArtistInfos');
        $property->setValue($artist, $signups);

        $active = $this->service->findActiveSignups($artist);

        // Slot has started, so signup should not be active
        $this->assertSame([], $active);
    }

    public function testFindActiveSignupsFallsBackToEventDateWhenNoSlotTime(): void
    {
        $artist = new Artist();

        // Create a future event
        $futureEvent = new \App\Entity\Event();
        $futureEvent->setEventDate(new \DateTimeImmutable('2025-07-01 20:00:00'));

        // Signup WITHOUT slot time set
        $signup = new EventArtistInfo();
        $signup->setEvent($futureEvent);
        // No setStartTime - should fall back to event date

        $signups = new ArrayCollection([$signup]);

        $reflection = new \ReflectionClass($artist);
        $property = $reflection->getProperty('eventArtistInfos');
        $property->setValue($artist, $signups);

        $active = $this->service->findActiveSignups($artist);

        $this->assertCount(1, $active);
        $this->assertSame($signup, $active[0]);
    }

    public function testFindSignupsByIdsReturnsOnlyMatchingIds(): void
    {
        $artist = $this->createArtistWithSignups(3);
        $signups = $artist->getEventArtistInfos()->toArray();

        $this->setSignupId($signups[0], 10);
        $this->setSignupId($signups[1], 20);
        $this->setSignupId($signups[2], 30);

        $found = $this->service->findSignupsByIds($artist, [10, 30]);

        $this->assertCount(2, $found);
        $this->assertContains($signups[0], $found);
        $this->assertContains($signups[2], $found);
        $this->assertNotContains($signups[1], $found);
    }

    public function testFindSignupsByIdsReturnsEmptyForEmptyIds(): void
    {
        $artist = $this->createArtistWithSignups(2);

        $found = $this->service->findSignupsByIds($artist, []);

        $this->assertSame([], $found);
    }

    public function testSyncMultipleCountsOnlySuccessfulSyncs(): void
    {
        $artist = new Artist();
        $artist->setName('Test');
        $artist->setType('DJ');

        $clone1 = new Artist();
        $clone2 = new Artist();

        $signup1 = new EventArtistInfo();
        $signup1->setArtist($artist);
        $signup1->setArtistClone($clone1);

        $signup2 = new EventArtistInfo();
        $signup2->setArtist($artist);
        $signup2->setArtistClone($clone2);

        $signupNoClone = new EventArtistInfo();
        $signupNoClone->setArtist($artist);
        // No clone - should fail

        $count = $this->service->syncMultiple([$signup1, $signup2, $signupNoClone]);

        $this->assertSame(2, $count);
        $this->assertSame('Test', $clone1->getName());
        $this->assertSame('Test', $clone2->getName());
    }

    private function createArtistWithSignups(int $count): Artist
    {
        $artist = new Artist();

        $signups = new ArrayCollection();
        for ($i = 0; $i < $count; ++$i) {
            $signup = new EventArtistInfo();
            $signups->add($signup);
        }

        $reflection = new \ReflectionClass($artist);
        $property = $reflection->getProperty('eventArtistInfos');
        $property->setValue($artist, $signups);

        return $artist;
    }

    private function setSignupId(EventArtistInfo $signup, int $id): void
    {
        $reflection = new \ReflectionClass($signup);
        $property = $reflection->getProperty('id');
        $property->setValue($signup, $id);
    }
}
