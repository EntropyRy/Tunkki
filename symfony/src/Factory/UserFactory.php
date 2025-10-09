<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\User;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<User>
 *
 * Rationale: Kept under src/ (not tests/) for parity with other domain factories:
 *  - Enables reuse in fixtures/seeding/console scripts without duplicating logic.
 *  - Lightweight (no side effects until invoked) so autoload cost is negligible.
 *  - Keeps construction idioms colocated with the entity for contributor discoverability.
 *  - Avoids drift between "test-only" builders and production assumptions.
 * If this ever gains heavy test-only behavior, we can relocate it to tests/ and document in TESTING.md.
 */
final class UserFactory extends PersistentObjectFactory
{
    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
     *
     * (Inject services here if/when needed.)
     */
    public function __construct()
    {
    }

    public static function class(): string
    {
        return User::class;
    }

    /**
     * Default field values for new User entities.
     */
    protected function defaults(): array
    {
        $now = new \DateTimeImmutable();

        return [
            // Deterministic timestamps aid in predictable assertions
            'CreatedAt' => $now,
            'UpdatedAt' => $now,
            // Short, unique, identifier-safe auth id
            'authId' => self::faker()->unique()->lexify('user_????????'),
            // Bcrypt hash for test password "password"
            'password' => password_hash('password', \PASSWORD_BCRYPT),
            // No elevated roles by default
            'roles' => [],
        ];
    }

    /**
     * Post-instantiation hooks (none currently).
     */
    #[\Override]
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(User $user): void {})
        ;
    }
}
