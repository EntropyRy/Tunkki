<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Member;
use App\Entity\User;
use App\Factory\MemberFactory;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * LoginHelperTrait (Test User Lifecycle Helper).
 *
 * Stable, factory-driven creation & reuse of User/Member pairs with in-memory cache.
 *
 * Goals:
 *  - Prevent duplicate user/member creation (unique constraint) via static email cache
 *  - Auto-recover from closed EntityManager (reset guard)
 *  - Factory-only fresh creation via MemberFactory (linked User auto-created; owning side set pre-flush)
 *  - Provide deterministic role merging & session stabilization
 *  - Keep diagnostic hooks minimal and opt-in
 *
 * Phases (createUserWithRoles):
 *  0. Static cache hit → repository find(id) → merge roles (no scans).
 *  1. Existing User by member email (merge roles).
 *  2. Existing Member (has User → merge; or orphan → create User + link).
 *  3. Fresh factory-only creation (MemberFactory auto-creates linked User).
 *
 * Invariants:
 *  - Returned User always has a non‑null Member (1:1 link).
 *  - No duplicate Member creation for same email within a process.
 *  - Roles stored without explicit ROLE_USER (Symfony adds it dynamically).
 *
 * Recovery:
 *  - UniqueConstraintViolationException during creation is caught once; we reset
 *    the manager and re-fetch the existing user by email (race resilience).
 *
 * Diagnostics:
 *  - Set TEST_USER_CREATION_DEBUG=1 to emit JSON lines to STDERR for each phase.
 *
 * NOTE:
 *  - Tests SHOULD prefer programmatic loginUser($user) after using this helper.
 *  - Where HTTP form login flows are specifically tested, this trait may still
 *    be used to stage prerequisite users (e.g. admin accounts).
 */
trait LoginHelperTrait
{
    /**
     * Static email cache (email lowercase → user id).
     *
     * @var array<string,int>
     */
    private static array $userEmailCache = [];

    /* ---------------------------------------------------------------------
     * Diagnostics
     * --------------------------------------------------------------------- */

    /**
     * @param array<string,mixed> $context
     */
    private function diagCreate(
        string $phase,
        ?string $email,
        array $roles,
        array $context = [],
    ): void {
        if (!getenv('TEST_USER_CREATION_DEBUG')) {
            return;
        }

        $payload =
            [
                'ts' => microtime(true),
                'phase' => $phase,
                'email' => $email,
                'roles' => $roles,
            ] + $context;

        @fwrite(
            \STDERR,
            '[LoginHelperTrait] '.
                json_encode($payload, \JSON_UNESCAPED_SLASHES).
                \PHP_EOL,
        );
    }

    /* ---------------------------------------------------------------------
     * Core Creation / Reuse
     * --------------------------------------------------------------------- */

    /**
     * Create (or reuse) a User with given roles. Email optional (auto‑generated if null).
     *
     * @param string[]              $roles
     * @param non-empty-string|null $email
     */
    protected function createUserWithRoles(
        array $roles,
        ?string $email = null,
    ): User {
        $email ??= \sprintf('user_%s@example.test', bin2hex(random_bytes(4)));
        $this->diagCreate('start', $email, $roles);

        // Guard: reopen/reset EM if closed
        $registry = static::getContainer()->get('doctrine');
        $em = $registry->getManager();
        if (!$em->isOpen()) {
            $this->diagCreate('entity-manager.closed-reset', $email, $roles);
            $this->resetManager();
        }

        $sanitizedRoles = $this->sanitizeRoles($roles);
        $cacheKey = strtolower($email);

        // Phase 0: Static cache
        if (isset(self::$userEmailCache[$cacheKey])) {
            $repo = $registry->getRepository(User::class);
            $cached = $repo->find(self::$userEmailCache[$cacheKey]);
            if ($cached instanceof User) {
                $merged = $this->mergeRoles(
                    $cached->getRoles(),
                    $sanitizedRoles,
                );
                $cached->setRoles($merged);
                $this->persistAndFlush($cached, 'cache-hit', $email);

                return $cached;
            }
            // Stale cache entry
            unset(self::$userEmailCache[$cacheKey]);
            if (getenv('TEST_ABORT_ON_DUP_USER')) {
                throw new \RuntimeException(\sprintf('Stale cache entry for email %s (id %s).', $email, (string) (self::$userEmailCache[$cacheKey] ?? 'n/a')));
            }
        }

        // Phase 1: Existing user by email
        if ($existingUser = $this->findUserByMemberEmail($email)) {
            $merged = $this->mergeRoles(
                $existingUser->getRoles(),
                $sanitizedRoles,
            );
            $existingUser->setRoles($merged);
            $this->persistAndFlush(
                $existingUser,
                'reuse-existing-user',
                $email,
            );

            return $existingUser;
        }

        // Phase 2: Existing member
        if ($existingMember = $this->findMemberByEmail($email)) {
            $attached = $existingMember->getUser();
            if ($attached instanceof User) {
                $merged = $this->mergeRoles(
                    $attached->getRoles(),
                    $sanitizedRoles,
                );
                $attached->setRoles($merged);
                $this->persistAndFlush(
                    $attached,
                    'reuse-attached-user',
                    $email,
                );

                return $attached;
            }

            return $this->attachUserToExistingMember(
                $existingMember,
                $sanitizedRoles,
                $email,
            );
        }

        // Safety re-check before creating new pair (avoid duplicate by email)
        if ($recheck = $this->findUserByMemberEmail($email)) {
            $merged = $this->mergeRoles($recheck->getRoles(), $sanitizedRoles);
            $recheck->setRoles($merged);
            $this->persistAndFlush(
                $recheck,
                'recheck-reuse-existing-user',
                $email,
            );

            return $recheck;
        }

        // Phase 3: Fresh pair via MemberFactory (auto-creates linked User)
        return $this->createFreshPairFactoryOnly($email, $sanitizedRoles);
    }

    /**
     * Factory-only fresh pair creation.
     *
     * 1) MemberFactory::new(['email' => $email])->create() auto-creates a linked User (owning side set pre-flush)
     * 2) Merge roles (if supplied) and flush once
     */
    private function createFreshPairFactoryOnly(
        string $email,
        array $roles,
    ): User {
        $this->diagCreate('fresh-factory.before', $email, $roles);

        try {
            $memberProxy = MemberFactory::new(['email' => $email])->create();
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            // Parallel/process race or prior test created same email. Recover by resetting EM and reloading.
            $this->diagCreate(
                'fresh-factory.unique-violation',
                $email,
                $roles,
                ['ex' => $e->getMessage()],
            );
            $this->resetManager();
            $recovered = $this->findUserByMemberEmail($email);
            if ($recovered instanceof User) {
                if ($roles) {
                    $recovered->setRoles(
                        $this->mergeRoles($recovered->getRoles(), $roles),
                    );
                    $this->persistAndFlush(
                        $recovered,
                        'fresh-factory.recovered.role-merge',
                        $email,
                    );
                } else {
                    $this->diagCreate(
                        'fresh-factory.recovered.no-role-change',
                        $email,
                        $recovered->getRoles(),
                    );
                }
                $this->cacheUser($recovered);

                return $recovered;
            }
            throw $e;
        }

        /** @var Member $member */
        $member = $memberProxy;
        $user = $member->getUser();
        if (!$user instanceof User) {
            throw new \RuntimeException('Invariant violation: MemberFactory should produce a linked User.');
        }

        if ($roles) {
            $user->setRoles($this->mergeRoles($user->getRoles(), $roles));
            $this->persistAndFlush($user, 'fresh-factory.role-merge', $email);
        } else {
            $this->diagCreate(
                'fresh-factory.no-role-change',
                $email,
                $user->getRoles(),
            );
        }

        $this->cacheUser($user);
        $this->diagCreate('fresh-factory.created', $email, $user->getRoles(), [
            'userId' => $user->getId(),
            'memberId' => $member->getId(),
        ]);

        return $user;
    }

    /**
     * Attach a new User to an existing orphan Member.
     *
     * @param string[] $roles
     */
    private function attachUserToExistingMember(
        Member $member,
        array $roles,
        string $email,
    ): User {
        $this->diagCreate('attach-orphan-member.start', $email, $roles, [
            'memberId' => $member->getId(),
        ]);

        try {
            // Create User manually (no factory) and link owning side BEFORE flush.
            $user = new User();
            $user->setPassword(password_hash('password', \PASSWORD_BCRYPT));
            $user->setRoles($roles);
            if (method_exists($user, 'setAuthId')) {
                $user->setAuthId(bin2hex(random_bytes(10)));
            }

            // Owning side first, then inverse side
            $user->setMember($member);
            if (method_exists($member, 'setUser')) {
                $member->setUser($user);
            }

            $this->persistAndFlush(
                $user,
                'attach-orphan-member.linked',
                $email,
                [
                    'userId' => $user->getId(),
                    'memberId' => $member->getId(),
                ],
            );

            if ($user->getMember() !== $member) {
                throw new \RuntimeException('Invariant violation linking orphan member.');
            }

            return $user;
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            $this->diagCreate(
                'attach-orphan-member.unique-violation',
                $email,
                $roles,
                ['ex' => $e->getMessage()],
            );
            $this->resetManager();
            $recovered = $this->findUserByMemberEmail($email);
            if ($recovered instanceof User) {
                $this->diagCreate(
                    'attach-orphan-member.recovered',
                    $email,
                    $recovered->getRoles(),
                    [
                        'userId' => $recovered->getId(),
                    ],
                );

                return $recovered;
            }
            throw $e;
        }
    }

    /* ---------------------------------------------------------------------
     * Public Helpers
     * --------------------------------------------------------------------- */

    /**
     * Login user by email (create if missing).
     *
     * @param string[] $rolesIfCreating Only applied if a new user must be created
     *
     * @return array{0:User,1:KernelBrowser}
     */
    protected function loginAsEmail(
        string $email,
        array $rolesIfCreating = [],
    ): array {
        $user = $this->getOrCreateUser($email, $rolesIfCreating);
        $client = $this->client;
        $client->loginUser($user);
        $this->stabilizeSessionAfterLogin();

        return [$user, $client];
    }

    /**
     * Login user ensuring they have at least the primary role (+ additional).
     *
     * @param string[] $additionalRoles
     *
     * @return array{0:User,1:KernelBrowser}
     */
    protected function loginAsRole(
        string $primaryRole,
        array $additionalRoles = [],
        ?string $email = null,
    ): array {
        $roles = array_values(
            array_unique(array_merge([$primaryRole], $additionalRoles)),
        );
        $targetEmail =
            $email ?? \sprintf('user_%s@example.test', bin2hex(random_bytes(4)));

        $user = $this->getOrCreateUser($targetEmail, $roles);
        $client = $this->client;
        $client->loginUser($user);
        $this->stabilizeSessionAfterLogin();

        return [$user, $client];
    }

    /**
     * Log in a user whose Member is marked as active (Member::getIsActiveMember() === true).
     *
     * If the user does not exist, it is created; then the Member is toggled active and flushed.
     *
     * Retries on unique email collisions by regenerating a random email (up to 3 attempts)
     * when no explicit email is provided by the caller.
     *
     * @return array{0:User,1:KernelBrowser}
     */
    protected function loginAsActiveMember(?string $email = null): array
    {
        $attempts = 0;
        $lastEx = null;

        do {
            ++$attempts;
            $candidate =
                $email ??
                \sprintf(
                    'activemember_%s@example.test',
                    bin2hex(random_bytes(4)),
                );
            try {
                // Create or fetch the user without logging in yet
                $user = $this->getOrCreateUser($candidate);

                // Ensure Member is marked active BEFORE login so the serialized token reflects it
                $member = $user->getMember();
                if (
                    $member instanceof Member
                    && !$member->getIsActiveMember()
                ) {
                    $em = static::getContainer()->get('doctrine')->getManager();
                    if (method_exists($member, 'setIsActiveMember')) {
                        $member->setIsActiveMember(true);
                    }
                    if (method_exists($em, 'persist')) {
                        $em->persist($member);
                    }
                    $em->flush();
                }

                // Now log in and stabilize session so token contains updated member state
                $client = $this->client;
                $client->loginUser($user);
                $this->stabilizeSessionAfterLogin();

                // Success path
                return [$user, $client];
            } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
                $lastEx = $e;

                // If the caller provided a fixed email, do not retry with a different one
                if (null !== $email) {
                    // Reset EM to avoid closed manager state on rethrow paths
                    $this->resetManager();
                    throw $e;
                }

                // Recover: reset EM and attempt to reuse an existing user with the candidate email
                $this->resetManager();
                $recovered = $this->findUserByMemberEmail($candidate);
                if ($recovered instanceof User) {
                    // Ensure active flag BEFORE login
                    $member = $recovered->getMember();
                    if (
                        $member instanceof Member
                        && !$member->getIsActiveMember()
                    ) {
                        $em = static::getContainer()
                            ->get('doctrine')
                            ->getManager();
                        if (method_exists($member, 'setIsActiveMember')) {
                            $member->setIsActiveMember(true);
                        }
                        if (method_exists($em, 'persist')) {
                            $em->persist($member);
                        }
                        $em->flush();
                    }

                    // Log in after state is correct and stabilize
                    $client = $this->client;
                    $client->loginUser($recovered);
                    $this->stabilizeSessionAfterLogin();

                    // Success path via recovered existing user
                    return [$recovered, $client];
                }

                // Otherwise loop and try again with a newly generated candidate
            }
        } while ($attempts < 3);

        if ($lastEx) {
            // Exhausted retries; rethrow the last exception for visibility
            throw $lastEx;
        }

        // Fallback (should not be reached): ensure active then login with a longer random suffix
        $user = $this->getOrCreateUser(
            \sprintf('activemember_%s@example.test', bin2hex(random_bytes(8))),
        );

        $member = $user->getMember();
        if ($member instanceof Member && !$member->getIsActiveMember()) {
            $em = static::getContainer()->get('doctrine')->getManager();
            if (method_exists($member, 'setIsActiveMember')) {
                $member->setIsActiveMember(true);
            }
            if (method_exists($em, 'persist')) {
                $em->persist($member);
            }
            $em->flush();
        }

        $client = $this->client;
        $client->loginUser($user);
        $this->stabilizeSessionAfterLogin();

        return [$user, $client];
    }

    /**
     * Log in a member with unverified email.
     *
     * Useful for testing permission boundaries where email verification status matters
     * (e.g., calendar configuration requires verified email).
     *
     * If the user does not exist, it is created; the Member's emailVerified flag
     * is explicitly set to FALSE and flushed before login.
     *
     * @return array{0:User,1:KernelBrowser}
     */
    protected function loginAsMemberWithUnverifiedEmail(?string $email = null): array
    {
        $candidate = $email ?? \sprintf('unverified_%s@example.test', bin2hex(random_bytes(4)));

        $user = $this->getOrCreateUser($candidate);

        $member = $user->getMember();
        if ($member instanceof Member) {
            $em = static::getContainer()->get('doctrine')->getManager();
            $member->setEmailVerified(false);
            $em->persist($member);
            $em->flush();
        }

        $client = $this->client;
        $client->loginUser($user);
        $this->stabilizeSessionAfterLogin();

        return [$user, $client];
    }

    /**
     * Log in a regular (non-active) member.
     *
     * Similar to loginAsActiveMember() but does NOT set isActiveMember=true.
     * Useful for testing permission boundaries where active membership status matters
     * (e.g., events with nakkiRequiredForTicketReservation flag).
     *
     * If the user does not exist, it is created; the Member's isActiveMember flag
     * is explicitly set to FALSE and flushed before login.
     *
     * Retries on unique email collisions by regenerating a random email (up to 3 attempts)
     * when no explicit email is provided by the caller.
     *
     * @return array{0:User,1:KernelBrowser}
     */
    protected function loginAsMember(?string $email = null): array
    {
        $attempts = 0;
        $lastEx = null;

        do {
            ++$attempts;
            $candidate =
                $email ??
                \sprintf(
                    'regularmember_%s@example.test',
                    bin2hex(random_bytes(4)),
                );
            try {
                // Create or fetch the user without logging in yet
                $user = $this->getOrCreateUser($candidate);

                // Ensure Member is marked as NON-active BEFORE login so the serialized token reflects it
                $member = $user->getMember();
                if (
                    $member instanceof Member
                    && $member->getIsActiveMember()
                ) {
                    $em = static::getContainer()->get('doctrine')->getManager();
                    if (method_exists($member, 'setIsActiveMember')) {
                        $member->setIsActiveMember(false);
                    }
                    if (method_exists($em, 'persist')) {
                        $em->persist($member);
                    }
                    $em->flush();
                }

                // Now log in and stabilize session so token contains updated member state
                $client = $this->client;
                $client->loginUser($user);
                $this->stabilizeSessionAfterLogin();

                // Success path
                return [$user, $client];
            } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
                $lastEx = $e;

                // If the caller provided a fixed email, do not retry with a different one
                if (null !== $email) {
                    // Reset EM to avoid closed manager state on rethrow paths
                    $this->resetManager();
                    throw $e;
                }

                // Recover: reset EM and attempt to reuse an existing user with the candidate email
                $this->resetManager();
                $recovered = $this->findUserByMemberEmail($candidate);
                if ($recovered instanceof User) {
                    // Ensure NON-active flag BEFORE login
                    $member = $recovered->getMember();
                    if (
                        $member instanceof Member
                        && $member->getIsActiveMember()
                    ) {
                        $em = static::getContainer()
                            ->get('doctrine')
                            ->getManager();
                        if (method_exists($member, 'setIsActiveMember')) {
                            $member->setIsActiveMember(false);
                        }
                        if (method_exists($em, 'persist')) {
                            $em->persist($member);
                        }
                        $em->flush();
                    }

                    // Log in after state is correct and stabilize
                    $client = $this->client;
                    $client->loginUser($recovered);
                    $this->stabilizeSessionAfterLogin();

                    // Success path via recovered existing user
                    return [$recovered, $client];
                }

                // Otherwise loop and try again with a newly generated candidate
            }
        } while ($attempts < 3);

        if ($lastEx) {
            // Exhausted retries; rethrow the last exception for visibility
            throw $lastEx;
        }

        // Fallback (should not be reached): ensure non-active then login with a longer random suffix
        $user = $this->getOrCreateUser(
            \sprintf('regularmember_%s@example.test', bin2hex(random_bytes(8))),
        );

        $member = $user->getMember();
        if ($member instanceof Member && $member->getIsActiveMember()) {
            $em = static::getContainer()->get('doctrine')->getManager();
            if (method_exists($member, 'setIsActiveMember')) {
                $member->setIsActiveMember(false);
            }
            if (method_exists($em, 'persist')) {
                $em->persist($member);
            }
            $em->flush();
        }

        $client = $this->client;
        $client->loginUser($user);
        $this->stabilizeSessionAfterLogin();

        return [$user, $client];
    }

    /**
     * Get or create a user for an email, merging roles if user exists.
     *
     * @param string[] $rolesIfCreating
     */
    protected function getOrCreateUser(
        string $email,
        array $rolesIfCreating = [],
    ): User {
        if ($existing = $this->findUserByMemberEmail($email)) {
            if ($rolesIfCreating) {
                $merged = $this->mergeRoles(
                    $existing->getRoles(),
                    $this->sanitizeRoles($rolesIfCreating),
                );
                $existing->setRoles($merged);
                $this->persistAndFlush(
                    $existing,
                    'merge-roles-existing',
                    $email,
                );
            } else {
                $this->diagCreate(
                    'reuse-existing-no-role-change',
                    $email,
                    $existing->getRoles(),
                );
            }

            return $existing;
        }

        return $this->createUserWithRoles(
            $this->sanitizeRoles($rolesIfCreating),
            $email,
        );
    }

    /**
     * Assert an authenticated token has a specified role.
     */
    protected function assertAuthenticatedUserHasRole(string $role): void
    {
        $ts = static::getContainer()->get('security.token_storage');
        if (!method_exists($ts, 'getToken')) {
            self::fail('Token storage missing; cannot assert role.');
        }
        $token = $ts->getToken();
        self::assertNotNull($token, 'No security token.');
        $user = $token->getUser();
        self::assertTrue(\is_object($user), 'Token has no user object.');
        /* @var User $user */
        self::assertContains(
            $role,
            $user->getRoles(),
            \sprintf(
                'Expected role %s; got [%s]',
                $role,
                implode(', ', $user->getRoles()),
            ),
        );
    }

    /* ---------------------------------------------------------------------
     * Lookups
     * --------------------------------------------------------------------- */

    protected function findUserByMemberEmail(string $email): ?User
    {
        /** @var ManagerRegistry $registry */
        $registry = static::getContainer()->get('doctrine');
        $repo = $registry->getRepository(User::class);

        // Cache fast-path
        $lower = strtolower($email);
        if (isset(self::$userEmailCache[$lower])) {
            $cached = $repo->find(self::$userEmailCache[$lower]);
            if ($cached instanceof User) {
                return $cached;
            }
            unset(self::$userEmailCache[$lower]); // stale
        }

        if (method_exists($repo, 'findOneByMemberEmail')) {
            /* @phpstan-ignore-next-line */
            return $repo->findOneByMemberEmail($email);
        }

        /** @var User[] $all */
        $all = $repo->findAll();
        foreach ($all as $u) {
            $m = $u->getMember();
            if (
                $m instanceof Member
                && 0 === strcasecmp($m->getEmail() ?? '', $email)
            ) {
                return $u;
            }
        }

        return null;
    }

    protected function findMemberByEmail(string $email): ?Member
    {
        /** @var ManagerRegistry $registry */
        $registry = static::getContainer()->get('doctrine');
        $repo = $registry->getRepository(Member::class);

        if (method_exists($repo, 'findOneBy')) {
            $match = $repo->findOneBy(['email' => $email]);
            if ($match instanceof Member) {
                return $match;
            }
        }

        /** @var Member[] $all */
        $all = $repo->findAll();
        foreach ($all as $m) {
            if (0 === strcasecmp($m->getEmail() ?? '', $email)) {
                return $m;
            }
        }

        return null;
    }

    /* ---------------------------------------------------------------------
     * Browser / Session
     * --------------------------------------------------------------------- */

    protected function newClient(): KernelBrowser
    {
        if (
            property_exists($this, 'siteAwareClient')
            && $this->siteAwareClient instanceof KernelBrowser
        ) {
            return $this->siteAwareClient;
        }
        if (
            property_exists($this, 'client')
            && $this->client instanceof KernelBrowser
        ) {
            return $this->client;
        }

        throw new \LogicException('Site-aware client not initialized. Call $this->initSiteAwareClient() in setUp() and use $this->client.');
    }

    /**
     * Ensure session holds serialized security token so subsequent requests (incl. wrapped SiteRequest)
     * retain authentication.
     */
    private function stabilizeSessionAfterLogin(): void
    {
        try {
            $container = static::getContainer();
            if (
                !$container->has('session')
                || !$container->has('security.token_storage')
            ) {
                return;
            }
            $session = $container->get('session');
            $tokenStorage = $container->get('security.token_storage');
            $token = $tokenStorage->getToken();
            if (!$token) {
                return;
            }
            if (
                method_exists($session, 'isStarted')
                && !$session->isStarted()
            ) {
                $session->start();
            }
            $firewall = 'main';
            $session->set('_security_'.$firewall, serialize($token));
            $session->save();

            $browser = null;
            if (
                property_exists($this, 'siteAwareClient')
                && $this->siteAwareClient instanceof KernelBrowser
            ) {
                $browser = $this->siteAwareClient;
            } elseif (
                property_exists($this, 'client')
                && $this->client instanceof KernelBrowser
            ) {
                $browser = $this->client;
            }
            if ($browser) {
                $browser
                    ->getCookieJar()
                    ->set(
                        new \Symfony\Component\BrowserKit\Cookie(
                            $session->getName(),
                            $session->getId(),
                            null,
                            '/',
                            'localhost',
                        ),
                    );
            }
        } catch (\Throwable $e) {
            @fwrite(
                \STDERR,
                '[LoginHelperTrait] session stabilization failed: '.
                    $e->getMessage().
                    \PHP_EOL,
            );
        }
    }

    /* ---------------------------------------------------------------------
     * Form-Based Registration (Controller-Parity Path)
     * --------------------------------------------------------------------- */

    /**
     * Register a new member/user pair using the real profile creation form
     * (ProfileController::newMember) to replicate production creation logic
     * (password hashing, code generation, authId population, emailVerified default, etc.).
     *
     * After successful form submission this method logs the user in (programmatic login)
     * so subsequent test steps can assume an authenticated session.
     *
     * Notes:
     *  - The controller redirects to the login route after successful creation; we
     *    follow that redirect chain implicitly by performing a second request if needed.
     *  - Roles are NOT part of the public registration flow; if $extraRoles are supplied,
     *    they are merged post‑creation and flushed.
     *  - Locale controls which localized path to hit (fi: /profiili/uusi, en: /en/profile/new).
     *
     * @return array{0:User,1:KernelBrowser}
     */
    protected function registerUserViaForm(
        string $email,
        string $plainPassword = 'Password123!',
        string $locale = 'fi',
        array $extraRoles = [],
        array $memberFieldOverrides = [],
    ): array {
        $client = $this->client;

        $path = 'fi' === $locale ? '/profiili/uusi' : '/en/profile/new';

        // 1. GET the registration form
        $crawler = $client->request('GET', $path);
        if ($client->getResponse()->getStatusCode() >= 400) {
            self::fail(
                \sprintf(
                    'Failed to load registration form (%s), HTTP %d',
                    $path,
                    $client->getResponse()->getStatusCode(),
                ),
            );
        }

        // 2. Locate the form (assume first form on page)
        $formNode = $crawler->filter('form')->first();
        self::assertGreaterThan(
            0,
            $formNode->count(),
            'Registration form not found on page.',
        );

        // 3. Build field data (respecting MemberType + embedded UserPasswordType)
        $baseMemberData = [
            'member[username]' => 'user_'.bin2hex(random_bytes(3)),
            'member[firstname]' => 'Test',
            'member[lastname]' => 'User',
            'member[email]' => $email,
            'member[phone]' => '+358000000',
            'member[locale]' => $locale,
            'member[CityOfResidence]' => 'City',

            'member[theme]' => 'dark',
            'member[user][plainPassword][first]' => $plainPassword,
            'member[user][plainPassword][second]' => $plainPassword,
        ];

        foreach ($memberFieldOverrides as $k => $v) {
            $baseMemberData[$k] = $v;
        }

        // 4. Submit form
        $form = $formNode->form($baseMemberData);
        $client->submit($form);

        // 5. Follow redirect(s) until non-redirect or guard
        $maxRedirects = 5;
        while (
            \in_array(
                $client->getResponse()->getStatusCode(),
                [301, 302, 303],
                true,
            )
            && $maxRedirects-- > 0
        ) {
            $location = $client->getResponse()->headers->get('Location');
            if (!$location) {
                break;
            }
            $client->request('GET', $location);
        }

        // 6. Fetch newly created user
        $created = $this->findUserByMemberEmail($email);
        self::assertNotNull($created, 'User not created via form flow.');
        self::assertInstanceOf(User::class, $created);

        // 7. Merge roles if provided (post-registration)
        if ($extraRoles) {
            $merged = $this->mergeRoles(
                $created->getRoles(),
                $this->sanitizeRoles($extraRoles),
            );
            $created->setRoles($merged);
            $this->persistAndFlush(
                $created,
                'form-registration-role-merge',
                $email,
            );
        }

        // 8. Programmatic login for test continuity
        $client->loginUser($created);
        $this->stabilizeSessionAfterLogin();

        // Probe one cheap GET to solidify session + SiteRequest context for this locale
        $probePath = 'fi' === $locale ? '/' : '/en/';
        $client->request('GET', $probePath);
        $status = $client->getResponse()->getStatusCode();
        if (\in_array($status, [301, 302, 303], true)) {
            $loc = $client->getResponse()->headers->get('Location');
            if ($loc) {
                $client->request('GET', $loc);
            }
        }

        return [$created, $client];
    }

    /* ---------------------------------------------------------------------
     * Utility
     * --------------------------------------------------------------------- */

    /**
     * Merge roles avoiding duplicates & explicit ROLE_USER (Symfony adds automatically).
     *
     * @param string[] $existing
     * @param string[] $incoming
     *
     * @return string[]
     */
    private function mergeRoles(array $existing, array $incoming): array
    {
        return array_values(
            array_filter(
                array_unique(
                    array_merge(
                        array_filter(
                            $existing,
                            static fn (string $r) => 'ROLE_USER' !== $r,
                        ),
                        $incoming,
                    ),
                ),
                static fn (string $r) => 'ROLE_USER' !== $r,
            ),
        );
    }

    /**
     * Normalize input roles (drop ROLE_USER, dedupe).
     *
     * @param string[] $roles
     *
     * @return string[]
     */
    private function sanitizeRoles(array $roles): array
    {
        return array_values(
            array_filter(
                array_unique($roles),
                static fn (string $r) => 'ROLE_USER' !== $r,
            ),
        );
    }

    /**
     * Persist (if managed) & flush user changes with diagnostic logging.
     *
     * @param array<string,mixed> $ctx
     */
    private function persistAndFlush(
        User $user,
        string $phase,
        string $email,
        array $ctx = [],
    ): void {
        $em = static::getContainer()->get('doctrine')->getManager();
        if (method_exists($em, 'isOpen') && !$em->isOpen()) {
            $this->diagCreate(
                'persist.em-closed-reset',
                $email,
                $user->getRoles(),
                $ctx,
            );
            $this->resetManager();
            $em = static::getContainer()->get('doctrine')->getManager();
        }

        try {
            $em->persist($user);
            $em->flush();
            $this->cacheUser($user);
            $this->diagCreate(
                $phase,
                $email,
                $user->getRoles(),
                $ctx + ['userId' => $user->getId()],
            );
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            // Another creation path won the race; recover by reloading existing user by email
            $this->diagCreate(
                'persist.unique-violation',
                $email,
                $user->getRoles(),
                ['ex' => $e->getMessage()] + $ctx,
            );
            $this->resetManager();
            $recovered = $this->findUserByMemberEmail($email);
            if ($recovered instanceof User) {
                $this->cacheUser($recovered);
                $this->diagCreate(
                    $phase.'.recovered',
                    $email,
                    $recovered->getRoles(),
                    $ctx + ['userId' => $recovered->getId()],
                );

                return;
            }
            throw $e;
        }
    }

    /**
     * Reset or clear Doctrine manager to recover.
     * Prefer clearing the open EM to keep Foundry's manager instance in sync; only reset when truly closed.
     */
    private function resetManager(): void
    {
        /** @var ManagerRegistry $registry */
        $registry = static::getContainer()->get('doctrine');
        $em = $registry->getManager();

        try {
            if (method_exists($em, 'isOpen') && $em->isOpen()) {
                // Prefer clearing over resetting to avoid swapping the manager instance mid-run
                $em->clear();
            } else {
                // Manager is closed or does not expose isOpen: perform a full reset
                $registry->resetManager();
                $em = $registry->getManager();
            }
        } catch (\Throwable $e) {
            @fwrite(
                \STDERR,
                '[LoginHelperTrait] resetManager encountered an error: '.
                    $e->getMessage().
                    \PHP_EOL,
            );
        }

        // Sync base test's $this->em if present (FixturesWebTestCase)
        if (property_exists($this, 'em')) {
            try {
                $this->em = $em;
            } catch (\Throwable $e) {
                @fwrite(
                    \STDERR,
                    '[LoginHelperTrait] resetManager failed to sync $this->em: '.
                        $e->getMessage().
                        \PHP_EOL,
                );
            }
        }
    }

    /**
     * Cache user (email -> id). Fail-fast (optional) if a different id already cached for same email.
     */
    private function cacheUser(User $user): void
    {
        $id = $user->getId();
        if (!$id) {
            return; // not yet persisted
        }
        $email = $user->getMember()?->getEmail();
        if (!$email) {
            return;
        }
        $key = strtolower($email);
        if (
            isset(self::$userEmailCache[$key])
            && self::$userEmailCache[$key] !== $id
        ) {
            if (getenv('TEST_ABORT_ON_DUP_USER')) {
                throw new \RuntimeException(\sprintf('cacheUser mismatch for %s existingId=%d newId=%d', $email, self::$userEmailCache[$key], $id));
            }
        }
        self::$userEmailCache[$key] = $id;
    }

    /**
     * Force-persist a fresh TestBrowserToken for the given user into the session and token storage,
     * and ensure the BrowserKit cookie jar carries the session cookie for subsequent requests.
     * Use this after programmatic login when the site-aware wrapper or firewall resets the token.
     */
    protected function forceAuthToken(
        User $user,
        string $firewallContext = 'main',
    ): void {
        try {
            $container = static::getContainer();
            if (
                !$container->has('session')
                || !$container->has('security.token_storage')
            ) {
                return;
            }

            $session = $container->get('session');
            if (
                method_exists($session, 'isStarted')
                && !$session->isStarted()
            ) {
                $session->start();
            }

            $roles = method_exists($user, 'getRoles') ? $user->getRoles() : [];
            $token = new \Symfony\Bundle\FrameworkBundle\Test\TestBrowserToken(
                $user,
                $firewallContext,
                $roles,
            );

            $tokenStorage = $container->get('security.token_storage');
            if (method_exists($tokenStorage, 'setToken')) {
                $tokenStorage->setToken($token);
            }

            $session->set('_security_'.$firewallContext, serialize($token));
            $session->save();

            $browser = null;
            if (
                property_exists($this, 'siteAwareClient')
                && $this->siteAwareClient instanceof KernelBrowser
            ) {
                $browser = $this->siteAwareClient;
            } elseif (
                property_exists($this, 'client')
                && $this->client instanceof KernelBrowser
            ) {
                $browser = $this->client;
            }

            if ($browser) {
                $browser
                    ->getCookieJar()
                    ->set(
                        new \Symfony\Component\BrowserKit\Cookie(
                            $session->getName(),
                            $session->getId(),
                            null,
                            '/',
                            'localhost',
                        ),
                    );
            }
        } catch (\Throwable $e) {
            @fwrite(
                \STDERR,
                '[LoginHelperTrait] forceAuthToken failed: '.
                    $e->getMessage().
                    \PHP_EOL,
            );
        }
    }

    /**
     * Sanity: Ensure trait used only in WebTestCase descendants.
     */
    protected static function assertWebTestCaseContext(): void
    {
        if (!is_subclass_of(static::class, WebTestCase::class)) {
            self::fail(
                \sprintf(
                    'LoginHelperTrait requires consuming class (%s) to extend %s.',
                    static::class,
                    WebTestCase::class,
                ),
            );
        }
    }
}
