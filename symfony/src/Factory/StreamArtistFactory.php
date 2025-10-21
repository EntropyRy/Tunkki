<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Artist;
use App\Entity\Stream;
use App\Entity\StreamArtist;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;

/**
 * StreamArtistFactory.
 *
 * Factory for creating StreamArtist entities in tests.
 *
 * States:
 *  - active(): Creates a stream artist that is currently active (no stoppedAt)
 *  - stopped(): Creates a stream artist that has been stopped (with stoppedAt)
 *
 * Association helpers:
 *  - forArtist(): Associate with a specific Artist
 *  - inStream(): Associate with a specific Stream
 *
 * Example usage:
 *   $streamArtist = StreamArtistFactory::new()
 *       ->forArtist($artist)
 *       ->inStream($stream)
 *       ->active()
 *       ->create();
 *
 * @extends PersistentObjectFactory<StreamArtist>
 */
final class StreamArtistFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return StreamArtist::class;
    }

    protected function defaults(): callable
    {
        return static function (): array {
            return [
                'artist' => ArtistFactory::new(),
                'stream' => StreamFactory::new()->online(),
                'stoppedAt' => null, // Active by default
            ];
        };
    }

    /**
     * Associate this StreamArtist with a specific Artist.
     */
    public function forArtist(Artist|Proxy $artist): static
    {
        return $this->with(['artist' => $artist]);
    }

    /**
     * Associate this StreamArtist with a specific Stream.
     */
    public function inStream(Stream|Proxy $stream): static
    {
        return $this->with(['stream' => $stream]);
    }

    /**
     * Mark this stream artist as active (no stoppedAt).
     */
    public function active(): static
    {
        return $this->with(['stoppedAt' => null]);
    }

    /**
     * Mark this stream artist as stopped (with stoppedAt timestamp).
     */
    public function stopped(): static
    {
        return $this->with(['stoppedAt' => new \DateTimeImmutable()]);
    }

    /**
     * Set a specific started time.
     */
    public function startedAt(\DateTimeImmutable $time): static
    {
        return $this->with(['startedAt' => $time]);
    }

    /**
     * Set a specific stopped time.
     */
    public function stoppedAt(\DateTimeImmutable $time): static
    {
        return $this->with(['stoppedAt' => $time]);
    }
}
