<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Event;
use App\Time\ClockInterface;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * EventFactory.
 *
 * Lightweight factory for creating coherent Event entities for tests.
 *
 * Goals:
 *  - Provide sensible defaults (future event, already published, internal URL).
 *  - Offer expressive states for common permutations (published/unpublished, external, signup enabled, past).
 *  - Keep performance high: no heavy relation creation by default.
 *
 * Example:
 *   $event = EventFactory::new()->create()->object();
 *   $past  = EventFactory::new()->finished()->create()->object();
 *   $ext   = EventFactory::new()->external()->create(); // external URL event
 *
 * NOTE: Prefer this factory over broad global fixtures for new tests.
 * Rationale: We intentionally keep this factory (and similar lightweight factories)
 * under src/ instead of tests/ because:
 *   - They may be reused by data fixtures, seeding commands, or future
 *     maintenance scripts without duplicating entity construction logic.
 *   - Foundry treats factories as part of the domain testing toolkit; colocating
 *     them with entities improves discoverability for contributors.
 *   - Autoload impact is negligible (pure PHP, no side effects until invoked).
 *   - Avoids introducing a parallel “test-only” construction layer that can drift
 *     from real-world persistence assumptions.
 * If a specific factory ever becomes strictly test-only and accumulates heavy
 * mocking/test-only helpers, we can migrate just that file to tests/ and
 * document the move in TESTING.md (keeping other shared factories in src/).
 *
 * @extends PersistentObjectFactory<Event>
 */
final class EventFactory extends PersistentObjectFactory
{
    public function __construct(private ClockInterface $clock)
    {
    }

    public static function class(): string
    {
        return Event::class;
    }

    /**
     * Default attribute set.
     *
     * We pick deterministic relationships between dates:
     *  - eventDate: +7 days
     *  - publishDate: -30 days (forced deep past for test clock stability)
     *  - artist sign-up window closed by default (disabled)
     */
    protected function defaults(): callable
    {
        $clock = $this->clock;

        return static function () use ($clock): array {
            $now = $clock->now();
            $eventDate = $now->modify('+7 days');
            $slug = strtolower(
                str_replace(
                    [' ', '.'],
                    '-',
                    trim(self::faker()->unique()->words(3, true)),
                ),
            );
            $slug = preg_replace('#[^a-z0-9\\-]+#', '-', $slug) ?: 'event-slug';

            return [
                'name' => self::faker()->sentence(3), // English name
                'nimi' => self::faker()->sentence(3), // Finnish name
                'type' => 'event',
                'eventDate' => $eventDate,
                'publishDate' => $now->modify('-30 days'),
                'url' => $slug,
                'template' => 'event.html.twig',
                'published' => true,
                'externalUrl' => false,
                'artistSignUpEnabled' => false,
                'artistSignUpStart' => null,
                'artistSignUpEnd' => null,
                'backgroundEffect' => null,
                'backgroundEffectOpacity' => null,
                'backgroundEffectPosition' => null,
                'backgroundEffectConfig' => null,
                'cancelled' => false,
                'ticketCount' => 0,
                'ticketsEnabled' => false,
                'ticketPrice' => null,
                // multiday flag removed from direct attribute hydration; use setMultiday(false) in afterInstantiate()
            ];
        };
    }

    /**
     * Post-instantiation adjustments.
     */
    #[\Override]
    protected function initialize(): static
    {
        return $this->afterInstantiate(function (Event $event): void {
            // Normalize publishDate so it never exceeds eventDate for default/generated fixtures.
            if (
                $event->getPublishDate()
                && $event->getPublishDate() > $event->getEventDate()
            ) {
                $eventDate = $event->getEventDate();
                $immutableEventDate =
                    $eventDate instanceof \DateTimeImmutable
                        ? $eventDate
                        : \DateTimeImmutable::createFromInterface($eventDate);
                $event->setPublishDate(
                    $immutableEventDate->modify('-30 minutes'),
                );
            }

            // Derive multiday flag from duration (>24h) or leave as-is if already explicitly forced true by a state (setMultiday called).
            if (
                !$event->getMultiday()
                && $event->getUntil() instanceof \DateTimeInterface
                && $event->getUntil()->getTimestamp() -
                    $event->getEventDate()->getTimestamp() >
                    86400
            ) {
                $event->setMultiday(true);
            }

            // If artist signup enabled but window missing, synthesize a reasonable window (ends 3 days before event).
            if (
                $event->getArtistSignUpEnabled()
                && (!$event->getArtistSignUpStart() instanceof \DateTimeImmutable
                    || !$event->getArtistSignUpEnd() instanceof \DateTimeImmutable)
            ) {
                $eventDate = $event->getEventDate();
                $immutableEventDate =
                    $eventDate instanceof \DateTimeImmutable
                        ? $eventDate
                        : \DateTimeImmutable::createFromInterface($eventDate);
                $event->setArtistSignUpStart(
                    $immutableEventDate->modify('-14 days'),
                );
                $event->setArtistSignUpEnd(
                    $immutableEventDate->modify('-3 days'),
                );
            }
        });
    }

    /* -----------------------------------------------------------------
     * States
     * ----------------------------------------------------------------- */

    /**
     * Mark the event as unpublished (draft).
     */
    public function unpublished(): static
    {
        $now = $this->now();

        return $this->with([
            'published' => false,
            // If unpublished but publishDate exists in past, adjust forward to avoid confusion
            'publishDate' => $now->modify('+1 day'),
        ]);
    }

    /**
     * Explicitly mark event as published (already default, provided for clarity/chain usage).
     */
    public function published(): static
    {
        $now = $this->now();

        return $this->with([
            'published' => true,
            'publishDate' => $now->modify('-5 minutes'),
        ]);
    }

    /**
     * Make the event external (externalUrl=true) where getUrlByLang() should return the raw URL.
     */
    public function external(?string $destination = null): static
    {
        return $this->with([
            'externalUrl' => true,
            'url' => $destination ??
                'https://example.org/'.self::faker()->unique()->slug(),
        ]);
    }

    /**
     * Enable artist signup with a plausible window.
     */
    public function signupEnabled(): static
    {
        $now = $this->now();

        return $this->with([
            'artistSignUpEnabled' => true,
            'artistSignUpStart' => $now->modify('-1 day'),
            'artistSignUpEnd' => $now->modify('+5 days'),
        ]);
    }

    /**
     * Mark event as already finished (past event date).
     */
    public function finished(): static
    {
        $past = $this->now()->modify('-5 days');

        return $this->with([
            'eventDate' => $past,
            'publishDate' => $this->now()->modify('-60 days'),
        ]);
    }

    /**
     * Add a basic background effect configuration.
     */
    public function withBackgroundEffect(
        string $effect = 'flowfields',
        int $opacity = 60,
        ?string $position = 'z-index:0;',
    ): static {
        return $this->with([
            'backgroundEffect' => $effect,
            'backgroundEffectOpacity' => $opacity,
            'backgroundEffectPosition' => $position,
            'backgroundEffectConfig' => json_encode(
                [
                    'particleCount' => 120,
                    'speed' => 1.0,
                ],
                \JSON_THROW_ON_ERROR,
            ),
        ]);
    }

    /**
     * Enable ticketing with optional price & count.
     */
    public function ticketed(int $count = 100, ?int $priceCents = 1500): static
    {
        return $this->with([
            'ticketsEnabled' => true,
            'ticketCount' => $count,
            'ticketPrice' => $priceCents,
        ]);
    }

    /**
     * Make event multiday (eventDate recognized as start).
     */
    public function multiday(int $days = 2): static
    {
        $start = $this->now()->modify('+5 days');
        $until = $start->modify(\sprintf('+%d days', max(1, $days - 1)));

        return $this->with([
            'eventDate' => $start,
            'until' => $until,
            // multiday flag will be applied via afterInstantiate()->setMultiday(true)
        ]);
    }

    /* -----------------------------------------------------------------
     * Alias / Composite Convenience States (Roadmap Additions)
     * ----------------------------------------------------------------- */

    /**
     * Alias for finished() to improve readability in some tests.
     */
    public function past(): static
    {
        return $this->finished();
    }

    /**
     * Composite: past and unpublished (draft that already occurred).
     * Useful for verifying filters excluding stale drafts.
     */
    public function pastUnpublished(): static
    {
        return $this->finished()->unpublished();
    }

    /**
     * Alias for unpublished() for teams preferring draft vocabulary.
     */
    public function draft(): static
    {
        return $this->unpublished();
    }

    /**
     * Alias for external(); more explicit in some contexts.
     */
    public function externalEvent(?string $destination = null): static
    {
        return $this->external($destination);
    }

    /**
     * Alias for ticketed() using the default parameters.
     */
    public function ticketedBasic(): static
    {
        return $this->ticketed();
    }

    /**
     * Signup window not yet open (enabled but start in the near future).
     * start = +10 minutes, end = +2 days.
     */
    public function signupWindowNotYetOpen(): static
    {
        $now = $this->now();

        return $this->with([
            'artistSignUpEnabled' => true,
            'artistSignUpStart' => $now->modify('+10 minutes'),
            'artistSignUpEnd' => $now->modify('+2 days'),
        ]);
    }

    /**
     * Signup window just opened (boundary start == now).
     */
    public function signupWindowJustOpened(): static
    {
        $now = $this->now();

        return $this->with([
            'artistSignUpEnabled' => true,
            'artistSignUpStart' => $now,
            'artistSignUpEnd' => $now->modify('+2 days'),
        ]);
    }

    /**
     * Signup window closing very soon (ends in 5 minutes).
     */
    public function signupWindowClosingSoon(): static
    {
        $now = $this->now();

        return $this->with([
            'artistSignUpEnabled' => true,
            'artistSignUpStart' => $now->modify('-2 days'),
            'artistSignUpEnd' => $now->modify('+5 minutes'),
        ]);
    }

    /**
     * Signup window already ended (enabled flag true but end in past).
     * Ensures EventTemporalStateService::isSignupOpen() => false.
     */
    public function signupWindowEnded(): static
    {
        $now = $this->now();

        return $this->with([
            'artistSignUpEnabled' => true,
            'artistSignUpStart' => $now->modify('-3 days'),
            'artistSignUpEnd' => $now->modify('-10 minutes'),
        ]);
    }

    /**
     * Past event with a window that would otherwise look open.
     * Ensures past-event condition overrides signup availability.
     */
    public function pastEventSignupWindowOpen(): static
    {
        $past = $this->now()->modify('-1 day');

        return $this->finished()->with([
            'artistSignUpEnabled' => true,
            'artistSignUpStart' => $past->modify('-14 days'),
            'artistSignUpEnd' => $past->modify('+2 days'),
        ]);
    }

    /**
     * Enable RSVP system for the event.
     * Allows anonymous users to RSVP via RSVPType form.
     */
    public function withRsvpEnabled(): static
    {
        return $this->with(['rsvpSystemEnabled' => true]);
    }

    private function now(): \DateTimeImmutable
    {
        return $this->clock->now();
    }
}
