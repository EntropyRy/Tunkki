<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;

/**
 * SessionInvalidationTest.
 *
 * Verifies that:
 *  1. Logging out clears the security token (no authenticated user afterwards).
 *  2. A subsequent login as a different user establishes a new, independent session.
 *  3. Roles from the first user do not “leak” into the second user’s authenticated context.
 *
 * Rationale (Task G / security boundary hardening):
 *  - Ensures there is no accidental reuse of the previous security token or session attributes.
 *  - Guards against subtle test pollution when tests are run in random order.
 *
 * Implementation Notes:
 *  - Uses programmatic login (LoginHelperTrait) for speed and determinism.
 *  - We rely on Symfony’s token storage to inspect the currently authenticated user & roles.
 *  - Logout is performed via GET on the configured logout path (/logout) which should trigger token invalidation.
 */
final class SessionInvalidationTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    public function testLogoutClearsAuthenticationToken(): void
    {
        $adminEmail = 'session-admin-'.bin2hex(random_bytes(4)).'@example.test';
        // Arrange: create & login an admin-capable user
        $adminUser = $this->getOrCreateUser($adminEmail, ['ROLE_ADMIN']);
        $client = $this->client;
        $client->loginUser($adminUser);

        // Assert: user is authenticated and has ROLE_ADMIN
        $this->assertTrue($this->isAuthenticated(), 'User should be authenticated after login.');
        $this->assertUserHasRole('ROLE_ADMIN');

        // Act: perform logout (configured in security firewall)
        $client->request('GET', '/logout');

        // Assert: authentication token should now be cleared
        $this->assertFalse(
            $this->isAuthenticated(),
            'Authentication token should be cleared after logout.'
        );
    }

    public function testSecondLoginAfterLogoutUsesFreshSessionWithoutRoleLeak(): void
    {
        $adminEmail = 'session-admin-'.bin2hex(random_bytes(4)).'@example.test';
        $normalEmail = 'session-user-'.bin2hex(random_bytes(4)).'@example.test';
        // Arrange #1: User A with elevated role
        $adminUser = $this->getOrCreateUser($adminEmail, ['ROLE_ADMIN']);
        $client = $this->client;
        $client->loginUser($adminUser);

        $this->assertTrue($this->isAuthenticated(), 'User A should be authenticated.');
        $this->assertUserHasRole('ROLE_ADMIN');

        $firstUserId = $this->currentUserId();
        $this->assertNotNull($firstUserId, 'First user ID should resolve.');

        // Act #1: Logout
        $client->request('GET', '/logout');
        $this->assertFalse(
            $this->isAuthenticated(),
            'Authentication token should be cleared after first logout.'
        );

        // Arrange #2: User B WITHOUT admin role
        $normalUser = $this->getOrCreateUser($normalEmail, []);
        $client->loginUser($normalUser);

        // Assert: authenticated as second user
        $this->assertTrue($this->isAuthenticated(), 'User B should be authenticated.');
        $secondUserId = $this->currentUserId();

        $this->assertNotNull($secondUserId, 'Second user ID should resolve.');
        $this->assertNotSame(
            $firstUserId,
            $secondUserId,
            'Second login should produce a different authenticated user ID.'
        );

        // Assert: no leaked ROLE_ADMIN (User B has only base roles)
        $this->assertUserLacksRole('ROLE_ADMIN');
    }

    /* -----------------------------------------------------------------
     * Helper Assertions / Utilities
     * ----------------------------------------------------------------- */

    private function isAuthenticated(): bool
    {
        $ts = static::getContainer()->get('security.token_storage');
        if (!method_exists($ts, 'getToken')) {
            return false;
        }
        $token = $ts->getToken();
        if (!$token) {
            return false;
        }

        $user = $token->getUser();

        return \is_object($user);
    }

    private function currentUserId(): ?int
    {
        $ts = static::getContainer()->get('security.token_storage');
        if (!method_exists($ts, 'getToken')) {
            return null;
        }
        $token = $ts->getToken();
        if (!$token) {
            return null;
        }
        $user = $token->getUser();

        return $user instanceof User ? $user->getId() : null;
    }

    private function assertUserHasRole(string $role): void
    {
        $roles = $this->currentUserRoles();
        $this->assertContains(
            $role,
            $roles,
            \sprintf(
                'Expected authenticated user to have role %s. Roles: [%s]',
                $role,
                implode(', ', $roles)
            )
        );
    }

    private function assertUserLacksRole(string $role): void
    {
        $roles = $this->currentUserRoles();
        $this->assertNotContains(
            $role,
            $roles,
            \sprintf(
                'Did not expect authenticated user to have role %s. Roles: [%s]',
                $role,
                implode(', ', $roles)
            )
        );
    }

    /**
     * @return string[]
     */
    private function currentUserRoles(): array
    {
        $ts = static::getContainer()->get('security.token_storage');
        if (!method_exists($ts, 'getToken')) {
            return [];
        }
        $token = $ts->getToken();
        if (!$token) {
            return [];
        }
        $user = $token->getUser();

        if ($user instanceof User) {
            return $user->getRoles();
        }

        return [];
    }
}
