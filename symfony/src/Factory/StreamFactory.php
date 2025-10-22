<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Stream;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * StreamFactory.
 *
 * Factory for creating Stream entities in tests.
 *
 * States:
 *  - online(): Creates a stream that is currently online
 *  - offline(): Creates a stream that is offline (default)
 *
 * Example usage:
 *   $stream = StreamFactory::new()->online()->create();
 *   $stream = StreamFactory::new()->create(); // offline by default
 *
 * @extends PersistentObjectFactory<Stream>
 */
final class StreamFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Stream::class;
    }

    protected function defaults(): callable
    {
        return static fn (): array => [
            'online' => false,
            'listeners' => 0,
            'filename' => '',
        ];
    }

    /**
     * Mark this stream as online.
     */
    public function online(): static
    {
        return $this->with(['online' => true]);
    }

    /**
     * Mark this stream as offline.
     */
    public function offline(): static
    {
        return $this->with(['online' => false]);
    }

    /**
     * Set the number of listeners.
     */
    public function withListeners(int $count): static
    {
        return $this->with(['listeners' => $count]);
    }

    /**
     * Set the filename.
     */
    public function withFilename(string $filename): static
    {
        return $this->with(['filename' => $filename]);
    }
}
