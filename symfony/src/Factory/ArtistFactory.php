<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Artist;
use App\Entity\Member;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;

/**
 * ArtistFactory.
 *
 * Factory for creating Artist entities in tests with clear, composable states.
 *
 * Conventions:
 *  - Lightweight defaults (minimal required fields only).
 *  - States for common permutations (dj(), band()).
 *  - Fluent association helper withMember() to attach an existing Member or create one.
 *
 * Example usage:
 *   $artist = ArtistFactory::new()->dj()->create()->object();
 *   $artist = ArtistFactory::new()->withMember($member)->band()->create()->object();
 *
 * You can override attributes directly:
 *   ArtistFactory::new(['name' => 'Foobar'])->create();
 *
 * Notes:
 *  - Association to Member is optional; some tests may not require it.
 *  - If additional domain constraints emerge (e.g. unique slug or code),
 *    extend defaults()/states accordingly.
 *
 * Rationale (why under src/ instead of tests/):
 *  - May be reused by data fixtures or seeding scripts without duplication.
 *  - Lightweight/no side effects until used; autoload cost negligible.
 *  - Keeps entity construction idioms colocated with domain types for discoverability.
 *  - Avoids divergence between "test-only" builders and production creation logic.
 *  - Future heavy, strictly test-only factories can be migrated individually to tests/ (documenting in TESTING.md).
 *
 * @extends PersistentObjectFactory<Artist>
 */
final class ArtistFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Artist::class;
    }

    /**
     * Default field values.
     *
     * Use a callable to avoid static faker caching between test processes.
     */
    protected function defaults(): callable
    {
        return static function (): array {
            $faker = self::faker();

            return [
                // Basic display name
                'name' => $faker->unique()->sentence(2),
                // Generic default type; refined by dj()/band() states
                'type' => 'band',
                // Add more attributes here if Artist gains new non-nullable fields.
            ];
        };
    }

    /**
     * Post-instantiation adjustments (normalization, derived defaults).
     */
    #[\Override]
    protected function initialize(): static
    {
        return $this
            ->afterInstantiate(static function (Artist $artist): void {
                // Normalize type casing defensively
                $type = strtolower((string) $artist->getType());
                if ('dj' === $type || 'band' === $type) {
                    $artist->setType($type);
                }

                // Synchronize inverse side: ensure Member->addArtist($artist) when a member is present.
                if ($artist->getMember() instanceof Member) {
                    $member = $artist->getMember();
                    // Avoid duplicate link if already present
                    if (!$member->getArtist()->contains($artist)) {
                        $member->addArtist($artist);
                    }
                }
            });
    }

    /* -----------------------------------------------------------------
     * States
     * ----------------------------------------------------------------- */

    /**
     * Mark artist as a DJ.
     */
    public function dj(): static
    {
        return $this->with(['type' => 'dj']);
    }

    /**
     * Explicitly mark artist as a band (default already, provided for chain clarity).
     */
    public function band(): static
    {
        return $this->with(['type' => 'band']);
    }

    /**
     * Attach a specific Member or create a fresh one if null provided.
     *
     * @param Member|Proxy<Member>|null $member
     */
    public function withMember(Member|Proxy|null $member = null): static
    {
        if (null === $member) {
            // Defer to MemberFactory if available; if not, user must pass an explicit Member.
            if (class_exists(MemberFactory::class)) {
                return $this->with([
                    'member' => MemberFactory::new()->english(),
                ]);
            }

            // If MemberFactory is absent, leave association unset (caller can override directly).
            return $this;
        }

        // If a Proxy is passed, Foundry will handle extracting the underlying object.
        return $this->with(['member' => $member]);
    }

    /**
     * Randomize type (useful for bulk test data).
     */
    public function randomType(): static
    {
        return $this->with([
            'type' => self::faker()->randomElement(['dj', 'band']),
        ]);
    }
}
