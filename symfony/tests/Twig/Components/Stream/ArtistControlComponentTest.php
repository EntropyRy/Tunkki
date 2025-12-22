<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components\Stream;

use App\Factory\ArtistFactory;
use App\Factory\MemberFactory;
use App\Factory\StreamArtistFactory;
use App\Factory\StreamFactory;
use App\Repository\StreamArtistRepository;
use App\Tests\Twig\Components\LiveComponentTestCase;
use App\Twig\Components\Stream\ArtistControl;

final class ArtistControlComponentTest extends LiveComponentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetStreams();
    }

    public function testMountWithoutAuthenticatedUserLeavesComponentIdle(): void
    {
        StreamFactory::new()->online()->create();

        $component = $this->mountComponent(ArtistControl::class);
        $component->render();

        /** @var ArtistControl $control */
        $control = $component->component();
        self::assertNull($control->member);
        self::assertFalse($control->isInStream);
    }

    public function testMountWithMemberWithoutArtistsDoesNotEnterStream(): void
    {
        $member = MemberFactory::new()->english()->create();

        $component = $this->mountComponent(ArtistControl::class);
        $component->actingAs($member->getUser());
        $component->render();

        /** @var ArtistControl $control */
        $control = $component->component();
        self::assertSame($member->getId(), $control->member?->getId());
        self::assertFalse($control->isInStream);
        self::assertNull($control->existingStreamArtist);
    }

    public function testCancelActionStopsExistingStreamArtist(): void
    {
        $stream = StreamFactory::new()->online()->create();
        $member = MemberFactory::new()->english()->create();
        $artist = ArtistFactory::new()->withMember($member)->dj()->create();

        $streamArtist = StreamArtistFactory::new()
            ->forArtist($artist)
            ->inStream($stream)
            ->active()
            ->create();

        $component = $this->mountComponent(ArtistControl::class);
        $component->actingAs($member->getUser());
        $component->render();

        /** @var ArtistControl $control */
        $control = $component->component();
        self::assertTrue($control->isInStream);
        self::assertNotNull($control->existingStreamArtist);

        $component->call('cancel');
        $updated = $component->component();
        self::assertFalse($updated->isInStream);
        self::assertNull($updated->existingStreamArtist);

        /** @var StreamArtistRepository $repository */
        $repository = self::getContainer()->get(StreamArtistRepository::class);
        $reloaded = $repository->find($streamArtist->getId());
        self::assertNotNull($reloaded?->getStoppedAt());
    }

    public function testCancelDoesNothingWhenNotInStream(): void
    {
        $stream = StreamFactory::new()->online()->create();
        $member = MemberFactory::new()->english()->create();
        $artist = ArtistFactory::new()->withMember($member)->dj()->create();

        $streamArtist = StreamArtistFactory::new()
            ->forArtist($artist)
            ->inStream($stream)
            ->active()
            ->create();

        $component = $this->mountComponent(ArtistControl::class);
        $component->actingAs($member->getUser());
        $component->render();

        /** @var ArtistControl $control */
        $control = $component->component();
        $component->set('isInStream', false);
        $component->render();

        $component->call('cancel');

        /** @var StreamArtistRepository $repository */
        $repository = self::getContainer()->get(StreamArtistRepository::class);
        $reloaded = $repository->find($streamArtist->getId());
        self::assertNull($reloaded?->getStoppedAt());
    }

    public function testSaveDoesNothingWhenMemberMissing(): void
    {
        StreamFactory::new()->online()->create();

        $component = $this->mountComponent(ArtistControl::class);
        $component->render();
        $component->call('save');

        /** @var ArtistControl $state */
        $state = $component->component();
        self::assertFalse($state->isInStream);
        self::assertNull($state->existingStreamArtist);
    }

    public function testCancelDoesNothingWhenMemberMissing(): void
    {
        StreamFactory::new()->online()->create();

        $component = $this->mountComponent(ArtistControl::class);
        $component->render();
        $component->call('cancel');

        /** @var ArtistControl $state */
        $state = $component->component();
        self::assertFalse($state->isInStream);
        self::assertNull($state->existingStreamArtist);
    }

    public function testSaveAddsArtistToStream(): void
    {
        $stream = StreamFactory::new()->online()->create();
        $member = MemberFactory::new()->english()->create();
        ArtistFactory::new()->withMember($member)->dj()->create();

        $component = $this->mountComponent(ArtistControl::class);
        $component->actingAs($member->getUser());
        $component->render();

        $component->submitForm([
            'stream_artist' => [
                'artist' => $member->getArtist()->first()->getId(),
            ],
        ], 'save');
        /** @var ArtistControl $state */
        $state = $component->component();

        self::assertTrue($state->isInStream);
        self::assertNotNull($state->existingStreamArtist);
    }

    public function testSaveStopsExistingActiveArtistForMember(): void
    {
        $stream = StreamFactory::new()->online()->create();
        $member = MemberFactory::new()->english()->create();
        $firstArtist = ArtistFactory::new()->withMember($member)->dj()->create();
        $secondArtist = ArtistFactory::new()->withMember($member)->dj()->create();

        $active = StreamArtistFactory::new()
            ->forArtist($firstArtist)
            ->inStream($stream)
            ->active()
            ->create();

        $component = $this->mountComponent(ArtistControl::class);
        $component->actingAs($member->getUser());
        $component->render();

        $component->set('isInStream', false);
        $component->render();

        $component->submitForm([
            'stream_artist' => [
                'artist' => $secondArtist->getId(),
            ],
        ], 'save');

        /** @var StreamArtistRepository $repository */
        $repository = self::getContainer()->get(StreamArtistRepository::class);
        $reloaded = $repository->find($active->getId());
        self::assertNotNull($reloaded?->getStoppedAt());
    }

    public function testSaveRemovesExistingArtistViaSaveAction(): void
    {
        $stream = StreamFactory::new()->online()->create();
        $member = MemberFactory::new()->english()->create();
        $artist = ArtistFactory::new()->withMember($member)->dj()->create();

        $existing = StreamArtistFactory::new()
            ->forArtist($artist)
            ->inStream($stream)
            ->active()
            ->create();

        $component = $this->mountComponent(ArtistControl::class);
        $component->actingAs($member->getUser());
        $component->render();

        $component->call('save');
        /** @var ArtistControl $state */
        $state = $component->component();
        self::assertFalse($state->isInStream);

        /** @var StreamArtistRepository $repository */
        $repository = self::getContainer()->get(StreamArtistRepository::class);
        $reloaded = $repository->find($existing->getId());
        self::assertNotNull($reloaded?->getStoppedAt());
    }

    public function testStreamEventsResetComponentState(): void
    {
        $stream = StreamFactory::new()->online()->create();
        $member = MemberFactory::new()->english()->create();
        ArtistFactory::new()->withMember($member)->dj()->create();

        $component = $this->mountComponent(ArtistControl::class);
        $component->actingAs($member->getUser());
        $component->render();

        /** @var ArtistControl $state */
        $state = $component->component();
        $state->onStreamStarted();
        $state->onStreamStopped();

        self::assertFalse($state->isOnline);
        self::assertFalse($state->isInStream);
    }

    public function testStreamStoppedMarksExistingStreamArtistAsStopped(): void
    {
        $stream = StreamFactory::new()->online()->create();
        $member = MemberFactory::new()->english()->create();
        $artist = ArtistFactory::new()->withMember($member)->dj()->create();

        $streamArtist = StreamArtistFactory::new()
            ->forArtist($artist)
            ->inStream($stream)
            ->active()
            ->create();

        $component = $this->mountComponent(ArtistControl::class);
        $component->actingAs($member->getUser());
        $component->render();

        /** @var ArtistControl $state */
        $state = $component->component();
        self::assertNotNull($state->existingStreamArtist);

        $state->onStreamStopped();

        /** @var StreamArtistRepository $repository */
        $repository = self::getContainer()->get(StreamArtistRepository::class);
        $reloaded = $repository->find($streamArtist->getId());
        self::assertNotNull($reloaded?->getStoppedAt());
        self::assertNull($state->stream);
    }

    private function resetStreams(): void
    {
        $repo = self::getContainer()->get(\App\Repository\StreamRepository::class);
        $repo->stopAllOnline();
    }
}
