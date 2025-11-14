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

    private function resetStreams(): void
    {
        $repo = self::getContainer()->get(\App\Repository\StreamRepository::class);
        $repo->stopAllOnline();
    }
}
