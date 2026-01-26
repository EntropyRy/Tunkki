<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Artist;
use App\Entity\Event;
use App\Entity\EventArtistInfo;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;

/**
 * EventArtistInfoFactory.
 *
 * Factory for creating EventArtistInfo entities (artist signups to events) with sensible defaults.
 *
 * Goals:
 *  - Provide defaults for common signup scenarios
 *  - Fluent helpers to attach Event and Artist
 *  - Support typical form states (pending, with clone, etc.)
 *
 * Example:
 *   $signup = EventArtistInfoFactory::new()
 *       ->forEvent($event)
 *       ->forArtist($artist)
 *       ->create();
 *
 * @extends PersistentObjectFactory<EventArtistInfo>
 */
final class EventArtistInfoFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return EventArtistInfo::class;
    }

    /**
     * Default attribute set.
     */
    protected function defaults(): array
    {
        return [
            'SetLength' => self::faker()->randomElement(['30 min', '45 min', '60 min', '90 min']),
            'WishForPlayTime' => self::faker()->optional()->time('H:i'),
            'freeWord' => self::faker()->optional()->sentence(),
            'stage' => self::faker()->optional()->randomElement(['Main Stage', 'Second Stage', 'Outdoor']),
            'agreeOnRecording' => self::faker()->boolean(80),
            // Event and Artist should be provided via states or direct create(['event' => ...])
        ];
    }

    /**
     * Post-instantiation: ensure artistClone is created if not explicitly set.
     */
    #[\Override]
    protected function initialize(): static
    {
        return $this->afterInstantiate(static function (EventArtistInfo $info): void {
            // If an Artist is attached but no artistClone exists, create one
            if ($info->getArtist() instanceof Artist && !$info->getArtistClone() instanceof Artist) {
                $original = $info->getArtist();
                $clone = clone $original;
                $clone->setMember(null); // Clone should not link back to Member
                $clone->setCopyForArchive(true);

                $info->setArtistClone($clone);
            }
        });
    }

    /* -----------------------------------------------------------------
     * States
     * ----------------------------------------------------------------- */

    /**
     * Attach to a specific Event.
     *
     * @param Event|Proxy<Event> $event
     */
    public function forEvent(Event|Proxy $event): static
    {
        return $this->with(['Event' => $event]);
    }

    /**
     * Attach to a specific Artist.
     *
     * @param Artist|Proxy<Artist> $artist
     */
    public function forArtist(Artist|Proxy $artist): static
    {
        return $this->with(['Artist' => $artist]);
    }

    /**
     * Set a specific set length.
     */
    public function withSetLength(string $length): static
    {
        return $this->with(['SetLength' => $length]);
    }

    /**
     * Set a specific start time wish.
     */
    public function withStartTimeWish(string $time): static
    {
        return $this->with(['WishForPlayTime' => $time]);
    }

    /**
     * Add a free-form note.
     */
    public function withNote(string $note): static
    {
        return $this->with(['freeWord' => $note]);
    }

    /**
     * Explicitly set the artistClone (for testing edge cases).
     *
     * @param Artist|Proxy<Artist> $clone
     */
    public function withArtistClone(Artist|Proxy $clone): static
    {
        return $this->with(['artistClone' => $clone]);
    }
}
