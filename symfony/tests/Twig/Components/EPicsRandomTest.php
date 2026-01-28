<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components;

use App\Tests\Support\EPics\FakeEPicsService;
use App\Twig\Components\EPicsRandom;

/**
 * Tests for EPicsRandom Live Component.
 *
 * Tests the component logic for displaying random photos
 * from the ePics gallery with configurable refresh interval.
 */
final class EPicsRandomTest extends LiveComponentTestCase
{
    private FakeEPicsService $fakeEPicsService;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var FakeEPicsService $service */
        $service = static::getContainer()->get('App\Service\EPicsServiceInterface');
        $this->fakeEPicsService = $service;
    }

    public function testMountLoadsPhotoFromService(): void
    {
        $this->fakeEPicsService->setHasPhoto(true);
        $this->fakeEPicsService->setPhotoData([
            'url' => 'https://epics.test/photo.jpg',
            'taken' => '2025-06-15T18:30:00+00:00',
        ]);

        $component = $this->mountComponent(EPicsRandom::class);
        $component->render();

        /** @var EPicsRandom $epics */
        $epics = $component->component();

        self::assertSame('https://epics.test/photo.jpg', $epics->photoUrl);
        self::assertSame('2025-06-15T18:30:00+00:00', $epics->takenAt);
    }

    public function testMountWithNoPhotoSetsNullValues(): void
    {
        $this->fakeEPicsService->setHasPhoto(false);

        $component = $this->mountComponent(EPicsRandom::class);
        $component->render();

        /** @var EPicsRandom $epics */
        $epics = $component->component();

        self::assertNull($epics->photoUrl);
        self::assertNull($epics->takenAt);
    }

    public function testMountSetsDefaultImageFromProps(): void
    {
        $component = $this->mountComponent(EPicsRandom::class, [
            'defaultImage' => '/images/custom-default.svg',
        ]);
        $component->render();

        /** @var EPicsRandom $epics */
        $epics = $component->component();

        self::assertSame('/images/custom-default.svg', $epics->defaultImage);
    }

    public function testMountSetsRefreshIntervalFromProps(): void
    {
        $component = $this->mountComponent(EPicsRandom::class, [
            'refreshInterval' => 5000,
        ]);
        $component->render();

        /** @var EPicsRandom $epics */
        $epics = $component->component();

        self::assertSame(5000, $epics->refreshInterval);
    }

    public function testGetImageUrlReturnsPhotoUrlWhenAvailable(): void
    {
        $this->fakeEPicsService->setHasPhoto(true);
        $this->fakeEPicsService->setPhotoData([
            'url' => 'https://epics.test/random.jpg',
            'taken' => null,
        ]);

        $component = $this->mountComponent(EPicsRandom::class);
        $component->render();

        /** @var EPicsRandom $epics */
        $epics = $component->component();

        self::assertSame('https://epics.test/random.jpg', $epics->getImageUrl());
    }

    public function testGetImageUrlReturnsDefaultWhenNoPhoto(): void
    {
        $this->fakeEPicsService->setHasPhoto(false);

        $component = $this->mountComponent(EPicsRandom::class, [
            'defaultImage' => '/images/fallback.svg',
        ]);
        $component->render();

        /** @var EPicsRandom $epics */
        $epics = $component->component();

        self::assertSame('/images/fallback.svg', $epics->getImageUrl());
    }

    public function testHasPhotoReturnsTrueWhenPhotoExists(): void
    {
        $this->fakeEPicsService->setHasPhoto(true);

        $component = $this->mountComponent(EPicsRandom::class);
        $component->render();

        /** @var EPicsRandom $epics */
        $epics = $component->component();

        self::assertTrue($epics->hasPhoto());
    }

    public function testHasPhotoReturnsFalseWhenNoPhoto(): void
    {
        $this->fakeEPicsService->setHasPhoto(false);

        $component = $this->mountComponent(EPicsRandom::class);
        $component->render();

        /** @var EPicsRandom $epics */
        $epics = $component->component();

        self::assertFalse($epics->hasPhoto());
    }

    public function testRefreshActionReloadsPhoto(): void
    {
        // Start with no photo
        $this->fakeEPicsService->setHasPhoto(false);

        $component = $this->mountComponent(EPicsRandom::class);
        $component->render();

        /** @var EPicsRandom $epics */
        $epics = $component->component();
        self::assertNull($epics->photoUrl);

        // Now configure service to return a photo
        $this->fakeEPicsService->setHasPhoto(true);
        $this->fakeEPicsService->setPhotoData([
            'url' => 'https://epics.test/new-photo.jpg',
            'taken' => '2025-12-25T10:00:00+00:00',
        ]);

        // Call refresh action
        $component->call('refresh');

        // Get updated component instance after action
        /** @var EPicsRandom $updated */
        $updated = $component->component();
        self::assertSame('https://epics.test/new-photo.jpg', $updated->photoUrl);
        self::assertSame('2025-12-25T10:00:00+00:00', $updated->takenAt);
    }

    public function testRenderedOutputContainsImageWithCorrectSrc(): void
    {
        $this->fakeEPicsService->setHasPhoto(true);
        $this->fakeEPicsService->setPhotoData([
            'url' => 'https://epics.test/rendered.jpg',
            'taken' => '2025-01-01T12:00:00+00:00',
        ]);

        $component = $this->mountComponent(EPicsRandom::class);
        $crawler = $component->render()->crawler();

        $img = $crawler->filter('img.rect-img');
        self::assertCount(1, $img);
        self::assertSame('https://epics.test/rendered.jpg', $img->attr('src'));
    }

    public function testRenderedOutputShowsLoadingWhenNoTakenAt(): void
    {
        $this->fakeEPicsService->setHasPhoto(false);

        $component = $this->mountComponent(EPicsRandom::class);
        $crawler = $component->render()->crawler();

        $badge = $crawler->filter('.badge');
        self::assertCount(1, $badge);
        self::assertCount(1, $crawler->filter('.epics-badge-loading'));
        self::assertMatchesRegularExpression('/\\.\\.$/', $badge->text());
    }

    public function testRenderedOutputShowsDateWhenTakenAtPresent(): void
    {
        $this->fakeEPicsService->setHasPhoto(true);
        $this->fakeEPicsService->setPhotoData([
            'url' => 'https://epics.test/photo.jpg',
            'taken' => '2025-06-15T18:30:00+00:00',
        ]);

        $component = $this->mountComponent(EPicsRandom::class);
        $crawler = $component->render()->crawler();

        $badge = $crawler->filter('.badge');
        self::assertCount(1, $badge);
        self::assertCount(1, $crawler->filter('.epics-badge-date'));
        $expectedDate = (new \DateTimeImmutable('2025-06-15T18:30:00+00:00'))
            ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
            ->format('j.n.Y');
        self::assertMatchesRegularExpression(
            '/^'.preg_quote($expectedDate, '/').', \\d{2}:\\d{2}$/',
            $badge->text(),
        );
    }

    public function testRenderedOutputHasShimmerClassWhenNoPhoto(): void
    {
        $this->fakeEPicsService->setHasPhoto(false);

        $component = $this->mountComponent(EPicsRandom::class);
        $crawler = $component->render()->crawler();

        $img = $crawler->filter('img.rect-img.shimmer');
        self::assertCount(1, $img);
    }

    public function testRenderedOutputNoShimmerClassWhenPhotoPresent(): void
    {
        $this->fakeEPicsService->setHasPhoto(true);

        $component = $this->mountComponent(EPicsRandom::class);
        $crawler = $component->render()->crawler();

        $img = $crawler->filter('img.rect-img.shimmer');
        self::assertCount(0, $img);
    }

    public function testRenderedOutputContainsPollingDataAttribute(): void
    {
        $component = $this->mountComponent(EPicsRandom::class, [
            'refreshInterval' => 10000,
        ]);
        $crawler = $component->render()->crawler();

        $container = $crawler->filter('[data-poll]');
        self::assertCount(1, $container);
        self::assertSame('delay(10000)|refresh', $container->attr('data-poll'));
    }

    public function testRenderedOutputContainsProgressBar(): void
    {
        $component = $this->mountComponent(EPicsRandom::class);
        $crawler = $component->render()->crawler();

        $progress = $crawler->filter('.progress');
        self::assertCount(1, $progress);

        $progressBar = $crawler->filter('.progress-bar');
        self::assertCount(1, $progressBar);
        self::assertMatchesRegularExpression(
            '/^animation: epics-countdown-[01] var\\(--epics-duration\\) linear forwards$/',
            (string) $progressBar->attr('style'),
        );
    }

    public function testRenderedOutputHasEpicsContainerClass(): void
    {
        $component = $this->mountComponent(EPicsRandom::class);
        $crawler = $component->render()->crawler();

        $container = $crawler->filter('.epics-container');
        self::assertCount(1, $container);
    }

    public function testRenderedOutputHasLoadingDataAttribute(): void
    {
        $component = $this->mountComponent(EPicsRandom::class);
        $crawler = $component->render()->crawler();

        $container = $crawler->filter('[data-loading]');
        self::assertCount(1, $container);
        self::assertSame('addClass(loading)', $container->attr('data-loading'));
    }

    public function testRenderedOutputHasDynamicDurationStyle(): void
    {
        $component = $this->mountComponent(EPicsRandom::class, [
            'refreshInterval' => 5000,
        ]);
        $crawler = $component->render()->crawler();

        $container = $crawler->filter('.epics-container');
        self::assertSame('--epics-duration: 5s', $container->attr('style'));
    }

    public function testDefaultValuesAreSetCorrectly(): void
    {
        $this->fakeEPicsService->setHasPhoto(true);

        $component = $this->mountComponent(EPicsRandom::class);
        $component->render();

        /** @var EPicsRandom $epics */
        $epics = $component->component();

        self::assertSame('/images/header-logo.svg', $epics->defaultImage);
        self::assertSame(8000, $epics->refreshInterval);
    }

    public function testPhotoDataWithNullTakenAt(): void
    {
        $this->fakeEPicsService->setHasPhoto(true);
        $this->fakeEPicsService->setPhotoData([
            'url' => 'https://epics.test/no-date.jpg',
            'taken' => null,
        ]);

        $component = $this->mountComponent(EPicsRandom::class);
        $component->render();

        /** @var EPicsRandom $epics */
        $epics = $component->component();

        self::assertSame('https://epics.test/no-date.jpg', $epics->photoUrl);
        self::assertNull($epics->takenAt);
        self::assertTrue($epics->hasPhoto());
    }
}
