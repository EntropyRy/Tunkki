<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Event;
use App\Entity\Happening;
use App\Entity\Member;
use App\Entity\User;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * HappeningFactory.
 *
 * Fixture‑free creation of Happening entities for functional & integration tests.
 *
 * Design Goals:
 *  - Eliminate reliance on legacy HappeningTestFixtures (Decision 2025-10-03 – fixture-free test suite).
 *  - Provide expressive states for "released" (public) vs "unreleased" (private) happenings.
 *  - Simplify ownership wiring (owner members) and event association.
 *  - Keep defaults minimal: an internal event context is optional (you attach an Event explicitly if needed).
 *
 * Usage Examples:
 *   $released = HappeningFactory::new()->released()->forEvent($event)->withOwner($member)->create();
 *   $private  = HappeningFactory::new()->unreleased()->forEvent($event)->create();
 *
 * Notes:
 *  - If you need a related Event, create it first via EventFactory and chain ->forEvent($event).
 *  - Owners (members) can be attached via withOwner()/withOwners(); if not provided, defaults to zero owners.
 *  - Slugs (Fi/En) are generated uniquely per instance (collision‑resistant) but readable.
 *  - Times default to +2 hours (future); adjust via at(\DateTimeInterface) or past()/soon()/imminent() states (future additions).
 *
 * Potential Future Extensions:
 *  - State for needsPreliminarySignUp true (and a sign-ups open window).
 *  - State for allowing/disallowing comments.
 *  - Factory for HappeningBooking to produce bookings with or without comments.
 *
 * @extends PersistentObjectFactory<Happening>
 */
final class HappeningFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Happening::class;
    }

    /**
     * Default attribute set (unreleased, zero signups, comments allowed).
     */
    protected function defaults(): callable
    {
        return static function (): array {
            $now = new \DateTimeImmutable();
            $time = $now->modify('+2 hours');

            $slugBase = static fn (string $prefix): string => self::normalizeSlug(
                sprintf('%s-%s', $prefix, bin2hex(random_bytes(3)))
            );

            return [
                'nameFi' => self::faker()->sentence(3),
                'nameEn' => self::faker()->sentence(3),
                'descriptionFi' => self::faker()->paragraph(),
                'descriptionEn' => self::faker()->paragraph(),
                'time' => $time,
                'needsPreliminarySignUp' => false,
                'needsPreliminaryPayment' => false,
                'paymentInfoFi' => null,
                'paymentInfoEn' => null,
                'type' => 'event',
                'maxSignUps' => 0,
                'slugFi' => $slugBase('fi'),
                'slugEn' => $slugBase('en'),
                'priceFi' => null,
                'priceEn' => null,
                'releaseThisHappeningInEvent' => false, // unreleased by default
                'signUpsOpenUntil' => null,
                'allowSignUpComments' => true,
            ];
        };
    }

    #[\Override]
    protected function initialize(): static
    {
        return $this->afterInstantiate(function (Happening $happening): void {
            // Ensure maxSignUps non-negative
            if ((int) $happening->getMaxSignUps() < 0) {
                $happening->setMaxSignUps(0);
            }

            // If signUpsOpenUntil is set but in the past and release flag is true, that's permissible.
            // (No automatic mutation here—tests should control window logic deliberately.)
        });
    }

    /* -----------------------------------------------------------------
     * State Modifiers
     * ----------------------------------------------------------------- */

    /**
     * Mark as publicly released (visible).
     */
    public function released(): static
    {
        return $this->with([
            'releaseThisHappeningInEvent' => true,
        ]);
    }

    /**
     * Explicitly mark as unreleased/private.
     */
    public function unreleased(): static
    {
        return $this->with([
            'releaseThisHappeningInEvent' => false,
        ]);
    }

    /**
     * Disable signup comments.
     */
    public function commentsDisabled(): static
    {
        return $this->with([
            'allowSignUpComments' => false,
        ]);
    }

    /**
     * Require preliminary sign-up (no payment).
     */
    public function needsSignUp(): static
    {
        return $this->with([
            'needsPreliminarySignUp' => true,
        ]);
    }

    /**
     * Require preliminary payment (implies preliminary sign-up).
     */
    public function needsPayment(): static
    {
        return $this
            ->needsSignUp()
            ->with([
                'needsPreliminaryPayment' => true,
            ]);
    }

    /**
     * Set a future sign-ups open window (closes after +1 day by default).
     */
    public function signUpsOpenWindow(?\DateTimeImmutable $until = null): static
    {
        $until ??= new \DateTimeImmutable()->modify('+1 day');

        return $this->with([
            'signUpsOpenUntil' => $until,
        ]);
    }

    /**
     * Set an explicit time for the happening.
     */
    public function at(\DateTimeInterface $time): static
    {
        return $this->with([
            'time' => $time,
        ]);
    }

    /**
     * Convenience: schedule in the near future (e.g., +30 minutes).
     */
    public function imminent(): static
    {
        return $this->at(new \DateTimeImmutable('+30 minutes'));
    }

    /**
     * Convenience: schedule in the past (useful for negative or historical assertions).
     */
    public function past(): static
    {
        return $this->at(new \DateTimeImmutable('-2 hours'));
    }

    /* -----------------------------------------------------------------
     * Association Helpers
     * ----------------------------------------------------------------- */

    /**
     * Associate to a given Event (establishes owning side).
     */
    public function forEvent(Event|EventFactory $event): static
    {
        /** @var Event $resolved */
        $resolved = $event instanceof EventFactory ? $event->create()->object() : $event;

        return $this->afterInstantiate(function (Happening $happening) use ($resolved): void {
            $happening->setEvent($resolved);
        });
    }

    /**
     * Attach a single owner (Member or User).
     */
    public function withOwner(Member|User $owner): static
    {
        return $this->afterInstantiate(function (Happening $happening) use ($owner): void {
            $member = $owner instanceof User ? $owner->getMember() : $owner;
            if ($member instanceof Member) {
                $happening->addOwner($member);
            }
        });
    }

    /**
     * Attach multiple owners (array of Member|User).
     *
     * @param array<int,Member|User> $owners
     */
    public function withOwners(array $owners): static
    {
        return $this->afterInstantiate(function (Happening $happening) use ($owners): void {
            foreach ($owners as $o) {
                $member = $o instanceof User ? $o->getMember() : $o;
                if ($member instanceof Member) {
                    $happening->addOwner($member);
                }
            }
        });
    }

    /* -----------------------------------------------------------------
     * Internal Utilities
     * ----------------------------------------------------------------- */

    private static function normalizeSlug(string $input): string
    {
        $slug = strtolower($input);
        $slug = preg_replace('#[^a-z0-9-]+#', '-', $slug) ?: $slug;
        $slug = preg_replace('#-+#', '-', $slug) ?: $slug;

        return trim($slug, '-');
    }
}
