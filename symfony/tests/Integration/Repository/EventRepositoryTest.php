<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Event;
use App\Factory\EventFactory;
use App\Repository\EventRepository;
use App\Time\ClockInterface;

/**
 * @covers \App\Repository\EventRepository
 *
 * All tests now create their own data via Foundry factories (no reliance on
 * legacy global fixture slugs like "test-event" or "unpublished-event").
 * This ensures isolation and clearer intent.
 */
final class EventRepositoryTest extends RepositoryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ensure isolation for this test case: start from a clean Event table
        // No blanket deletion of events here; rely on per-test scoping via unique types.
        $this->em()->clear();
    }

    private function subjectRepo(): EventRepository
    {
        /** @var EventRepository $r */
        $r = $this->em()->getRepository(Event::class);

        return $r;
    }

    private function clock(): ClockInterface
    {
        /** @var ClockInterface $clock */
        $clock = static::getContainer()->get(ClockInterface::class);

        return $clock;
    }

    private function futureEventLowerBound(): \DateTimeImmutable
    {
        return $this->clock()->now()->modify('-30 hours');
    }

    /* ---------------------------------------------------------------------
     * Tests
     * --------------------------------------------------------------------- */

    public function testGetSitemapEventsExcludesUnpublishedAndExternal(): void
    {
        // Arrange (controlled dataset)
        $published = EventFactory::new()
            ->published()
            ->create([
                'url' => 'sitemap-visible',
            ]);

        $unpublished = EventFactory::new()
            ->unpublished()
            ->create([
                'url' => 'sitemap-draft',
            ]);

        $external = EventFactory::new()
            ->external('https://example.com/sitemap-external')
            ->create();

        // Act
        $events = $this->subjectRepo()->getSitemapEvents();

        // Assert
        self::assertIsArray($events);
        $ids = array_map(static fn (Event $e) => $e->getId(), $events);

        self::assertContains(
            $published->getId(),
            $ids,
            'Published internal event should appear in sitemap results.',
        );
        self::assertNotContains(
            $unpublished->getId(),
            $ids,
            'Unpublished event must be excluded from sitemap results.',
        );
        self::assertNotContains(
            $external->getId(),
            $ids,
            'External event must be excluded from sitemap results.',
        );

        // Order check: descending by EventDate
        $returnedDates = array_map(
            static fn (Event $e) => $e->getEventDate()?->getTimestamp(),
            $events,
        );
        $sorted = $returnedDates;
        rsort($sorted, \SORT_NUMERIC);
        self::assertSame(
            $sorted,
            $returnedDates,
            'Sitemap events must be ordered by EventDate DESC.',
        );
    }

    public function testGetRssEventsIncludesExternalAndPublishedOnly(): void
    {
        // Arrange
        $internal = EventFactory::new()
            ->published()
            ->create([
                'url' => 'rss-internal',
            ]);
        $external = EventFactory::new()
            ->external('https://example.com/rss-external')
            ->create();
        $unpublished = EventFactory::new()
            ->unpublished()
            ->create([
                'url' => 'rss-draft',
            ]);

        // Act
        $events = $this->subjectRepo()->getRSSEvents();
        self::assertIsArray($events);

        $ids = array_map(static fn (Event $e) => $e->getId(), $events);

        self::assertContains(
            $internal->getId(),
            $ids,
            'Published internal event should appear.',
        );
        self::assertContains(
            $external->getId(),
            $ids,
            'External published event should appear.',
        );
        self::assertNotContains(
            $unpublished->getId(),
            $ids,
            'Unpublished event must not appear.',
        );
    }

    public function testGetFutureEventsSkipsUnpublishedPastAnnouncement(): void
    {
        // Arrange dataset
        $futurePublished = EventFactory::new()
            ->published()
            ->create([
                'url' => 'future-published',
            ]);

        $futureUnpublished = EventFactory::new()
            ->unpublished()
            ->create([
                'url' => 'future-unpublished',
            ]);

        $pastPublished = EventFactory::new()
            ->finished()
            ->published()
            ->create([
                'url' => 'past-published',
            ]);

        $announcement = EventFactory::new()
            ->published()
            ->create([
                'url' => 'future-announcement',
                'type' => 'Announcement',
            ]);

        // Act
        $future = $this->subjectRepo()->getFutureEvents();
        self::assertIsArray($future);
        self::assertNotEmpty($future);

        $ids = array_map(static fn (Event $e) => $e->getId(), $future);

        self::assertContains(
            $futurePublished->getId(),
            $ids,
            'Future published event should be present.',
        );
        self::assertNotContains(
            $futureUnpublished->getId(),
            $ids,
            'Unpublished future event must be excluded.',
        );
        self::assertNotContains(
            $pastPublished->getId(),
            $ids,
            'Past published event must be excluded.',
        );
        self::assertNotContains(
            $announcement->getId(),
            $ids,
            'Announcement type event must be excluded.',
        );

        // Invariant checks for each returned event
        $threshold = $this->futureEventLowerBound();
        foreach ($future as $evt) {
            self::assertTrue(
                $evt->getPublished(),
                'All future events must be published.',
            );
            self::assertTrue(
                $evt->getEventDate() > $threshold,
                'EventDate must be > now - 30 hours.',
            );
            self::assertNotSame(
                'Announcement',
                $evt->getType(),
                'Type Announcement must be filtered out.',
            );
        }
    }

    public function testGetUnpublishedFutureEventsContainsOnlyUnpublished(): void
    {
        // Arrange
        $unpublishedFuture = EventFactory::new()
            ->unpublished()
            ->create([
                'url' => 'future-draft',
            ]);
        $unpublishedPast = EventFactory::new()
            ->finished()
            ->unpublished()
            ->create([
                'url' => 'past-draft',
            ]);
        $publishedFuture = EventFactory::new()
            ->published()
            ->create([
                'url' => 'future-public',
            ]);

        // Act
        $unpub = $this->subjectRepo()->getUnpublishedFutureEvents();

        // Assert
        self::assertIsArray($unpub);
        $ids = array_map(static fn (Event $e) => $e->getId(), $unpub);

        self::assertContains(
            $unpublishedFuture->getId(),
            $ids,
            'Future unpublished event should be present.',
        );
        self::assertNotContains(
            $unpublishedPast->getId(),
            $ids,
            'Past unpublished event should not be present.',
        );
        self::assertNotContains(
            $publishedFuture->getId(),
            $ids,
            'Published future event should not be present.',
        );

        foreach ($unpub as $evt) {
            self::assertFalse(
                $evt->getPublished(),
                'All returned events must be unpublished.',
            );
            self::assertTrue(
                $evt->getEventDate() > $this->futureEventLowerBound(),
                'Returned unpublished future event must still be future per repository rule.',
            );
        }
    }

    public function testFindOneEventByTypeReturnsLatestPublishedOfType(): void
    {
        // Use a unique type to avoid interference with pre-existing data
        $type = 'repo_type_'.substr(md5((string) microtime(true)), 0, 8);

        // Arrange: three events of the same unique type
        $older = EventFactory::new()
            ->published()
            ->create([
                'url' => 'older',
                'eventDate' => new \DateTimeImmutable('+2 days'),
                'type' => $type,
            ]);
        $middle = EventFactory::new()
            ->published()
            ->create([
                'url' => 'middle',
                'eventDate' => new \DateTimeImmutable('+4 days'),
                'type' => $type,
            ]);
        $latest = EventFactory::new()
            ->published()
            ->create([
                'url' => 'latest',
                'eventDate' => new \DateTimeImmutable('+6 days'),
                'type' => $type,
            ]);
        // Unpublished future event of same unique type should not affect result
        EventFactory::new()
            ->unpublished()
            ->create([
                'url' => 'future-draft-ignore',
                'eventDate' => new \DateTimeImmutable('+10 days'),
                'type' => $type,
            ]);

        // Act
        $found = $this->subjectRepo()->findOneEventByType($type);

        // Assert
        self::assertInstanceOf(Event::class, $found);
        self::assertSame(
            $latest->getId(),
            $found->getId(),
            'Should return the latest published event of requested type.',
        );
    }

    public function testFindEventBySlugAndYear(): void
    {
        // Arrange
        $targetYear = (int) new \DateTimeImmutable('+15 days')->format('Y');
        $slug = 'lookup-slug-'.substr(md5((string) microtime(true)), 0, 8);
        $target = EventFactory::new()
            ->published()
            ->create([
                'url' => $slug,
                'eventDate' => new \DateTimeImmutable(
                    \sprintf('%d-05-10 12:00:00', $targetYear),
                ),
            ]);

        // Act
        $found = $this->subjectRepo()->findEventBySlugAndYear(
            $slug,
            $targetYear,
        );

        // Assert
        self::assertInstanceOf(Event::class, $found);
        self::assertSame($target->getId(), $found->getId());
    }

    public function testFindPublicEventsByTypeIncludesKnownSlug(): void
    {
        // Use a unique type to avoid interference
        $type = 'repo_festival_'.substr(md5((string) microtime(true)), 0, 8);

        // Arrange
        $included = EventFactory::new()
            ->published()
            ->create([
                'url' => 'type-visible',
                'type' => $type,
            ]);
        EventFactory::new()
            ->unpublished()
            ->create([
                'url' => 'type-hidden-draft',
                'type' => $type,
            ]);

        // Act
        $events = $this->subjectRepo()->findPublicEventsByType($type);

        // Assert
        self::assertIsArray($events);
        $urls = array_map(static fn (Event $e) => $e->getUrl(), $events);
        self::assertContains(
            'type-visible',
            $urls,
            'Published type slug must be present.',
        );
        self::assertNotContains(
            'type-hidden-draft',
            $urls,
            'Unpublished slug must not be present.',
        );
    }

    public function testCountDoneDecreasesWhenEventCancelled(): void
    {
        // Use a unique URL prefix to scope counts to only this test's data
        $prefix = 'repo_cancel_'.substr(md5((string) microtime(true)), 0, 8);

        // Arrange
        $created = EventFactory::new()
            ->published()
            ->create([
                'url' => $prefix,
                'eventDate' => new \DateTimeImmutable('+3 days'),
            ]);
        // Reload the just-created entity via Doctrine to guarantee we update a managed instance
        $active = $this->em()
            ->getRepository(Event::class)
            ->findOneBy(['url' => $prefix]);
        $before = (int) $this->em()
            ->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from(Event::class, 'e')
            ->where('e.cancelled = :cancelled')
            ->andWhere('e.url LIKE :prefix')
            ->setParameter('cancelled', false)
            ->setParameter('prefix', $prefix.'%')
            ->getQuery()
            ->getSingleScalarResult();

        // Act: cancel the event
        $active->setCancelled(true);
        $this->em()->flush();
        // Clear EM to avoid stale state impacting repository count queries
        $this->em()->clear();

        // Assert
        $afterNotCancelled = (int) $this->em()
            ->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from(Event::class, 'e')
            ->where('e.cancelled = :cancelled')
            ->andWhere('e.url LIKE :prefix')
            ->setParameter('cancelled', false)
            ->setParameter('prefix', $prefix.'%')
            ->getQuery()
            ->getSingleScalarResult();
        self::assertSame(
            $before - 1,
            $afterNotCancelled,
            'Count of non-cancelled events should decrease by 1 after cancellation.',
        );
    }

    public function testFindPublicEventsByTypeGatesByPublishDate(): void
    {
        // Use a unique type to avoid interference with other tests
        $type = 'repo_announce_'.substr(md5((string) microtime(true)), 0, 8);

        // Visible: published and publishDate in the past
        EventFactory::new()
            ->published()
            ->create([
                'url' => 'pub-type-visible',
                'type' => $type,
            ]);

        // Hidden: published but publishDate in the future
        EventFactory::new()
            ->published()
            ->create([
                'url' => 'pub-type-future',
                'type' => $type,
                'publishDate' => new \DateTimeImmutable('+1 day'),
            ]);

        // Hidden: draft (published flag false)
        EventFactory::new()
            ->unpublished()
            ->create([
                'url' => 'pub-type-draft',
                'type' => $type,
            ]);

        // Act
        $events = $this->subjectRepo()->findPublicEventsByType($type);

        // Assert
        self::assertIsArray($events);
        $urls = array_map(static fn (Event $e) => $e->getUrl(), $events);
        self::assertContains(
            'pub-type-visible',
            $urls,
            'Past-publishDate published event should be present.',
        );
        self::assertNotContains(
            'pub-type-future',
            $urls,
            'Future publishDate must not be present.',
        );
        self::assertNotContains(
            'pub-type-draft',
            $urls,
            'Draft should not be present.',
        );
    }
}
