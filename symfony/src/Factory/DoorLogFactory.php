<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\DoorLog;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * DoorLogFactory.
 *
 * Factory for creating DoorLog entities in tests.
 *
 * @extends PersistentObjectFactory<DoorLog>
 */
final class DoorLogFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return DoorLog::class;
    }

    /**
     * Default field values.
     */
    protected function defaults(): callable
    {
        return static fn(): array => [
            'member' => MemberFactory::new()->active(),
            'message' => self::faker()->optional()->sentence(),
            'createdAt' => new \DateTimeImmutable(),
        ];
    }

    /**
     * Create a door log with a specific message.
     */
    public function withMessage(string $message): static
    {
        return $this->with(['message' => $message]);
    }

    /**
     * Create a door log without a message.
     */
    public function withoutMessage(): static
    {
        return $this->with(['message' => null]);
    }

    /**
     * Create a recent door log (within specified hours ago).
     */
    public function recent(int $hoursAgo = 1): static
    {
        return $this->with([
            'createdAt' => new \DateTimeImmutable("-{$hoursAgo} hours"),
        ]);
    }

    /**
     * Create an old door log (more than 4 hours ago).
     */
    public function old(): static
    {
        return $this->with([
            'createdAt' => new \DateTimeImmutable('-5 hours'),
        ]);
    }
}
