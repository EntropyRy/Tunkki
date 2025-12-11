# CLAUDE.md
Guidance & Command Reference for AI Assistant + Contributors
Project: Tunkki (Symfony 7, PHP 8.4+)

This document defines how an automated assistant (and humans) should interact with the repository safely and consistently. It encodes conventions, allowed operations, and canonical commands. Treat it as an execution contract.

---

## 1. Core Principles

1. Never guess paths – discover (or list) before reading/modifying.
2. Prefer existing tooling (Makefile targets, factories, test helpers) over ad‑hoc scripts.
3. Avoid introducing broad suppressions (static analysis, mutation, linters). Always document exceptions.
4. Maintain isolation: no cross‑test state leakage, no global fixture dependencies.
5. Use structural or semantic assertions (selectors, domain invariants) rather than brittle substrings.
6. Locale strategy: Finnish has no prefix; English uses `/en`. Admin area AND public routes follow this rule (except OAuth endpoints, which should remain canonical `/oauth*` — see §7.4).
7. All quality tooling (PHPStan, Infection) runs inside the Docker FPM container (PHP 8.4).

---

## 2. Environment & Execution

Canonical container (service name): `fpm`
Working directory inside container: `/var/www/symfony` (the repo root is bind‑mounted there).

Do NOT run composer or analysis tools on the host if PHP < required version.

Container PHP Usage (Explicit):
All PHP, console, and quality tool commands MUST be executed inside the Docker FPM container (service: fpm). The working directory inside the container is /var/www/symfony (the project root). Always invoke commands like:

docker compose exec -T fpm php ./bin/console cache:clear
docker compose exec -T fpm php vendor/bin/phpunit
docker compose exec -T fpm php vendor/bin/infection --filter=src/Security
docker compose exec -T fpm php ./bin/console doctrine:migrations:migrate --no-interaction

Short Form:
Use make targets which internally wrap docker compose exec -T fpm php. Do NOT run ./bin/console or vendor/bin tools directly on the host shell unless the host PHP version satisfies (>= 8.4) and mirrors container extensions (preferred: always use container).

Rationale:
- Ensures consistent PHP (8.4+) features & extensions.
- Prevents platform_check.php failures due to mismatched host PHP versions.
- Keeps mutation/static analysis parity with CI.

Invariant:
If you see platform or version mismatch errors (e.g. “requires PHP >= 8.4”), re-run the command inside the container using the pattern above.

---

## 3. Makefile Targets (Preferred Interface)

| Purpose               | Command                              | Notes |
|-----------------------|---------------------------------------|-------|
| Full test suite       | `make test`                           | Functional + unit |
| Unit tests only       | `make test-unit`                      | Fast check pre-commit |
| Functional only       | `make test-functional`               | DB + HTTP kernel |
| Coverage              | `make coverage`                       | Requires Xdebug/PCOV enabled in container |
| Mutation (exploratory)| `make infection FILTER=src/Security`  | Start narrow, see §8 |
| Mutation (baseline)   | `make infection-baseline`             | Baseline run |
| Static analysis full  | `make stan`                           | Uses level=5 unless overridden |
| Static analysis fast  | `make stan-fast`                      | Analyzes `src/` only |
| Clean caches          | `make clean`                          | Coverage + Infection + PHPStan caches |
| Doctor (env sanity)   | `make doctor`                         | Lists services & tool presence |

Override example:
```
make stan PHP_EXEC="docker compose exec -T fpm php" PHPSTAN_LEVEL=5
```

---

## 4. Test Suite Conventions

| Aspect               | Rule |
|----------------------|------|
| Client               | Always use `SiteAwareKernelBrowser` for functional tests. |
| Isolation            | Powered by DAMADoctrineTestBundle transactions. No persistent cross‑test DB state. |
| Data creation        | Use Zenstruck Foundry factories. No broad global fixtures for new tests. |
| Helper traits        | `tests/Support/` (LoginHelperTrait, LocaleDataProviderTrait, MetaAssertionTrait, FormErrorAssertionTrait). |
| Locales              | Use data providers for bilingual cases; Finnish route unprefixed, English under `/en/`. |
| Assertions           | Prefer `assertResponseIsSuccessful`, `assertSelectorExists`, structural DOM queries. |
| Negative coverage    | Include invalid form submissions, missing/expired windows, unauthorized access. |
| Security boundaries  | Explicit tests for role denial, session invalidation, CSRF, unverified flows. |
| Substring assertions | Do not use raw HTML substrings. If a micro invariant isn’t exposed structurally, add a semantic selector in the template (classes/ids) and assert via selectors. |

---

## 5. Static Analysis (PHPStan)

Current configuration file: `symfony/phpstan.neon`
Baseline policy: *No broad baseline*. Fix high-signal issues first.

Priority remediation order:
1. Generics (@extends) for Sonata Admin & CRUD controllers (Task 31D).
2. Nullability mismatches (entity fields vs PHP types).
3. Return / param type completions (avoid implicit mixed).
4. DateTimeImmutable consistency (no inadvertent mutable DateTime).
5. Undefined symbol/property access.
6. Dead private properties / unused injections.

Generics Annotation Pattern:
Add to each Admin:
```
/**
 * @extends AbstractAdmin<App\Entity\Event>
 */
final class EventAdmin extends AbstractAdmin
```
Controllers:
```
/**
 * @extends CRUDController<App\Entity\Event>
 */
final class EventAdminController extends CRUDController
```

Fast run (subset):
```
make stan-fast
```

Level stepping:
- Initial triage: level=5 (if noise too high at max).
- After generics & nullability fixes: raise to `max`.

Ignore policy:
- Each ignore must include rationale + expiration review date.
- No wildcard ignores on entire directories.

---

## 6. Mutation Testing (Infection)

Config: `symfony/infection.json.dist`
Initial scope: `src/Repository`, `src/Security` (fast feedback).
Run (exploratory):
```
make infection FILTER=src/Security
```
Baseline:
```
make infection-baseline
```

Do not raise MSI thresholds until:
1. High-value survivors addressed.
2. Structural negative tests stable.

Threshold introduction stages (example):
- Stage 0: `--min-msi=0`
- Stage 1: Soft reporting only
- Stage 2: Enforce `min-msi` ~ (current - 5%)
- Stage 3: Add covered MSI gate
- Stage 4: PR gating against diff (optional future enhancement)

---

## 7. Routing & Locale Policies

### 7.1 Public Locale Strategy
- Finnish (default): no prefix
- English: `/en/` prefix

### 7.2 Admin Bilingual
- Accept `/admin/...` and `/en/admin/...` (security unified via `^/(en/)?admin/`).
- Tests must cover both positive and negative (privileged / unprivileged) paths.

### 7.3 Event URLs
Internal events:
- FI: `/{year}/{slug}`
- EN: `/en/{year}/{slug}`
External events:
- `externalUrl=true` => `getUrlByLang()` returns raw external URL (passthrough)
- No expectation of internal localized pages resolving

### 7.4 OAuth Endpoints (Canonical)
- SHOULD remain unprefixed: `/oauth`, `/oauth/authorize`, `/oauth/check_*`
- If prefixed variants appear (`/en/oauth/...`), prefer to 301 redirect or 404 (avoid dual valid surfaces).
- Consent page locale determined via user preference or Accept-Language, **not** path prefix.

---

## 8. Negative Path Coverage (Checklist)

| Domain Area     | Examples Implemented / Needed |
|-----------------|--------------------------------|
| Authentication  | Invalid password, unknown email, CSRF missing/invalid |
| Session         | Session invalidation test (logout vs second login) |
| Forms (Member)  | Duplicate email, invalid email, short password, mismatch |
| Artist Signup   | Window not yet open, ended, event in past |
| Event Forms     | (If public form exists) Invalid date, external mismatch (TODO) |
| Event URL       | External passthrough, bilingual divergence |
| Security Roles  | Non-privileged admin denial, bilingual admin denial |

---

## 9. Substring Assertion Purge

Audit patterns:
```
grep -R "assertStringContainsString" tests/
grep -R "strpos(" tests/
grep -R "assertTrue(.*!empty" tests/
```
Policy:
- Do not use substring assertions in tests.
- If a page lacks stable selectors to assert on, update templates/components to provide semantic, stable selectors (classes/ids) and assert structurally.

---

## 10. Adding a New Test (Recipe)

**See**: TESTING.md Section 22 for comprehensive step-by-step guide with examples.

Quick checklist:
1. Decide layer:
   - Pure logic? => Unit (no kernel)
   - HTTP / DB / routing? => Functional (use SiteAwareKernelBrowser)
2. Create entities via Foundry factories (no shared fixture reliance).
3. If locale-sensitive => add data provider `[['fi'], ['en']]`.
4. Assertions:
   - Use `assertResponseIsSuccessful()`
   - Use `assertSelectorExists()` or `assertSelectorTextContains()`
   - Do not use raw HTML scanning. If selectors are missing, add semantic classes/ids to templates and assert structurally.
5. Negative scenario:
   - At least one invalid input / access denial variant.
6. No environment variable mutation; prefer service overrides or configuration.

**Additional Resources**:
- TESTING.md Section 15a: Factory State Catalog (canonical states reference)
- TESTING.md Section 23: Test Smells & Anti-Patterns
- TESTING.md Section 22a: Example end-to-end functional test

---

## 10A. Testing Patterns & Best Practices

This section documents established testing patterns from the test suite refactor (2025-10-02 through 2025-10-17).

### Assertion Patterns

**SiteAwareKernelBrowser Assertions** (Added 2025-10-17):
```php
// Use client-based assertions for Sonata multisite compatibility
$this->client->assertSelectorExists('form[name="cart"]');
$this->client->assertSelectorTextContains('.event-name', 'Test Event');

// NOT: $this->assertSelectorExists() - doesn't work with custom browser
```

**FixturesWebTestCase Helpers** (Added 2025-10-17):
```php
// Authentication state assertions
$this->assertNotAuthenticated('User should not be authenticated after failed login');
$this->assertAuthenticated('User should be authenticated after successful login');

// Ensure client ready (seeds a lightweight request if needed)
$this->ensureClientReady();

// Create fresh client (reuses initialized site-aware client)
$client = $this->newClient();
```

**Structural vs Substring**:
```php
// GOOD: Structural selector
$this->client->assertSelectorExists('.ticket-price');

// BAD: Substring scanning
$this->assertStringContainsString('<div class="ticket-price">', $response->getContent());
```

### Factory Usage Patterns

**Event + Product Creation**:
```php
$event = EventFactory::new()->published()->ticketed()->create([
    'url' => 'test-event-'.uniqid('', true),
    'ticketPresaleStart' => $realNow->modify('-1 day'),
    'ticketPresaleEnd' => $realNow->modify('+7 days'),
]);

$product = ProductFactory::new()->ticket()->forEvent($event)->create([
    'nameFi' => 'Tavallinen Lippu',
    'quantity' => 50,
    'amount' => 1500, // €15.00 in cents
]);
```

**Checkout Lifecycle**:
```php
// Create checkout in specific state
$checkout = CheckoutFactory::new()->open()->forCart($cart)->create();
$expired = CheckoutFactory::new()->expired()->forCart($cart)->create();
$completed = CheckoutFactory::new()->completed()->forCart($cart)->create();
```

**Cart with Items**:
```php
$item = CartItemFactory::new()->forProduct($product)->withQuantity(3)->create();
$cart = CartFactory::new()->withItems([$item])->create(['email' => 'test@example.com']);
```

### Time Handling Pattern (Dual Clock)

Tests requiring temporal logic use a dual time pattern:
```php
private function getDates(): array
{
    $realNow = new \DateTimeImmutable(); // For Entity methods using real time
    $clock = static::getContainer()->get(\App\Time\ClockInterface::class);
    $testNow = $clock->now(); // For services using ClockInterface

    return [$realNow, $testNow];
}

// Usage
[$realNow, $testNow] = $this->getDates();
$event = EventFactory::new()->create([
    'publishDate' => $testNow->modify('-5 minutes'), // Uses ClockInterface
    'ticketPresaleStart' => $realNow->modify('-1 day'), // Uses Entity method
]);
```

**Rationale**:
- `realNow`: For raw entity fields or legacy helpers that still rely on direct `new \DateTimeImmutable()` instantiations.
- `testNow`: For domain services like `EventTemporalStateService` that inject `ClockInterface`
- This allows deterministic testing while respecting existing architecture

### Login & Authentication Patterns

**Login Helper Usage**:
```php
// Login as specific member
$member = MemberFactory::new()->active()->create();
$this->loginAsMember($member);

// Login by email (finds or creates user)
$this->loginAsEmail('test@example.com');

// After login, seed new client for subsequent requests
$this->seedClientHome('fi');
```

**Security Behavior Assertions**:
```php
// Anonymous user denied → 302 redirect
$this->client->request('GET', '/admin/dashboard');
$response = $this->client->getResponse();
$this->assertSame(302, $response->getStatusCode());
$this->assertSame('http://localhost/login', $response->headers->get('Location'));

// Authenticated user denied → 403 Forbidden
$this->loginAsMember($regularMember);
$this->client->request('GET', '/admin/dashboard');
$this->assertResponseStatusCodeSame(403);
```

### Locale & Bilingual Testing

**Data Provider Pattern**:
```php
use PHPUnit\Framework\Attributes\DataProvider;

#[DataProvider('localeProvider')]
public function testShopAccessibleInBothLocales(string $locale): void
{
    $this->seedClientHome($locale);
    $shopPath = $locale === 'en'
        ? '/en/2025/test-event/kauppa'
        : '/2025/test-event/kauppa';

    $this->client->request('GET', $shopPath);
    $this->assertResponseIsSuccessful();
}

public static function localeProvider(): array
{
    return [['fi'], ['en']];
}
```

**Locale Routing Conventions**:
- Finnish: unprefixed (e.g., `/2025/event-slug/kauppa`)
- English: `/en/` prefix (e.g., `/en/2025/event-slug/kauppa`)
- Admin routes: both `/admin/` and `/en/admin/` accepted

### Negative Path Coverage

Every feature test SHOULD include at least one negative scenario:

**Form Validation**:
```php
// Invalid email
$this->client->submit($form, ['cart' => ['email' => 'not-an-email']]);
$this->assertResponseStatusCodeSame(422); // Unprocessable Entity
```

**Access Control**:
```php
// Presale not started
$event = EventFactory::new()->create([
    'ticketPresaleStart' => $realNow->modify('+2 days'), // Future
]);
$this->client->request('GET', $shopPath);
$this->assertResponseStatusCodeSame(302); // Redirect to login
```

**Timing Windows**:
```php
// Expired presale
$event = EventFactory::new()->create([
    'ticketPresaleEnd' => $realNow->modify('-1 day'), // Past
]);
$this->client->request('GET', $shopPath);
$this->assertResponseStatusCodeSame(302);
```

### Stripe ID Validation Pattern

Enforce test mode IDs in factories and tests:
```php
// ProductFactory defaults
'stripeId' => 'prod_test_'.$uniqueSuffix,
'stripePriceId' => 'price_test_'.$uniqueSuffix,

// CheckoutFactory defaults
'stripeSessionId' => 'cs_test_'.$uniqueSuffix,

// Validation test pattern
$this->assertMatchesRegularExpression(
    '/^prod_test_[a-zA-Z0-9]+$/',
    $product->getStripeId(),
    'Product stripeId should match test mode pattern'
);
```

### Anti-Patterns (Avoid)

**Don't: Create client without setUp initialization**:
```php
// BAD
public function testSomething(): void
{
    $client = static::createClient(); // Bypasses SiteRequest wrapping
    // ...
}

// GOOD
protected function setUp(): void
{
    parent::setUp();
    $this->initSiteAwareClient(); // Properly wrapped
    $this->seedClientHome('fi');
}
```

**Don't: Rely on test execution order**:
```php
// BAD - depends on previous test creating data
public function testEditEvent(): void
{
    $event = $this->em()->getRepository(Event::class)->findOneBy(['url' => 'some-event']);
    // ...
}

// GOOD - create data per test
public function testEditEvent(): void
{
    $event = EventFactory::new()->create(['url' => 'test-event']);
    // ...
}
```

**Don't: Use raw HTML substring assertions**:
```php
// BAD
$this->assertStringContainsString('<h1>Welcome</h1>', $response->getContent());

// GOOD
$this->client->assertSelectorTextContains('h1', 'Welcome');
```

**Don't: Hardcode dates**:
```php
// BAD
$event->setTicketPresaleStart(new \DateTimeImmutable('2025-10-01'));

// GOOD
[$realNow, $testNow] = $this->getDates();
$event->setTicketPresaleStart($realNow->modify('-1 day'));
```

### Troubleshooting Common Issues

**"A client must be set to make assertions"**:
- Fix: Call `$this->initSiteAwareClient()` in setUp
- Fix: Call `$this->ensureClientReady()` before assertions

**"request() method must be called before getCrawler()"**:
- Fix: Make at least one request before accessing crawler
- Fix: Use `ensureClientReady()` to seed lightweight request

**"RuntimeException: host path by locale strategy"**:
- Fix: Ensure using SiteAwareKernelBrowser (not raw KernelBrowser)
- Fix: Call `initSiteAwareClient()` in setUp

**"BadMethodCallException: request() must be called before getResponse()"**:
- Fix: ensureClientReady() now catches this and seeds a request
- Pattern: Always call ensureClientReady() before assertions if unsure

**302 redirect instead of expected 403**:
- Context: Symfony security redirects anonymous users to /login
- Fix: Either login first, or assert 302 + redirect location

---

## 11. Service Overrides (Test Performance & Determinism)

Implemented:
- Lower password hashing cost (test env)
- Null mailer transport
- Fixed clock service

Pending improvements:
- Disable external notifiers fully
- Enforce synchronous messenger (if async transport introduced)

Configuration Conventions:
- Do NOT create new subdirectories under config/ for environment-specific overrides.
- Use when@test (and when@dev / when@prod as needed) conditional blocks inside existing YAML files (e.g. services.yaml) instead of adding parallel directory trees.
- Prefer a single services.yaml (plus minimal bundle recipe files) with concise when@test: sections for test-only service wiring (e.g. FixedClock, null transports).
- Only introduce a new file if a third-party bundle recipe mandates it; otherwise consolidate.

ClockInterface Rationale:
- The custom App\Time\ClockInterface (and AppClock) abstracts time retrieval to:
  * Remove scattered new \DateTime()/\DateTimeImmutable() calls (improves test determinism).
  * Enable FixedClock / FrozenClock in tests via when@test without altering domain/service code.
  * Support future temporal refactors (Event/Happening presale & signup window logic) and mutation testing of time‑based branches.
  * Provide a seam for offset/testing tools or tracing (e.g. logging all “now” calls).

Adoption Guidelines:
1. New services requiring the current time MUST depend on ClockInterface.
2. Existing entities should not directly inject the clock; instead domain services compute time‑dependent state and pass values in (facilitates unit tests).
3. When replacing new DateTime() calls in services, preserve timezone assumptions; prefer $clock->now()->modify(...) over constructing multiple “now” instances.
4. Tests requiring temporal control add a FixedClock binding inside when@test in services.yaml (or extend the existing test override file) rather than creating a separate config directory.

Documentation:
- Any new override MUST be documented in TESTING.md (Service Overrides section) with purpose and rollback strategy.

---

## 12. Static Analysis Generics Task (31D / 31E)

Status Tracking:
- All Sonata Admin subclasses must specify their entity type via `@extends`.
- After completion: re-run PHPStan (level=5) → record reduced error count → escalate to next categories.

Example for unknown entity (temporary):
```
/**
 * @extends AbstractAdmin<object>
 */
```
Replace `object` with concrete entity ASAP.

---

## 13. Mutation + Static Analysis Synergy

Use mutation survivors to inform missing unit tests:
- Escaped conditional mutants => Add focused unit test on branch logic.
- Recurrent repository logic escapes => Add repository tests with precise fixtures/factories.

Static analysis hints for mutation:
- Missing generics => Poor inference => Harder to write tight tests.

---

## 14. Decision Log Integration

When making structural decisions (e.g., locale canonicalization for OAuth), append a dated entry in `todo.md` “Decision Log” and (optionally) mirror a short summary here.

Format:
```
YYYY-MM-DD: DECISION: <summary>. RATIONALE: <why>. IMPACT: <tests/config to update>.
```
Example:
```
2025-10-04: DECISION: Adopt ParaTest-backed parallel execution for the test suite via Makefile (auto-detect CPUs; disable with USE_PARALLEL=0). RATIONALE: Reduce wall-clock time while retaining isolation (DAMA transactions) and CMS baseline determinism (ensureCmsBaseline idempotent). IMPACT: Makefile targets run paratest when available; added test-parallel-debug helper; document usage in TESTING.md; monitor and fall back to serial on flakiness (USE_PARALLEL=0).
```

---

## 15. Common Pitfalls (Avoid)

| Pitfall                          | Replace With |
|----------------------------------|--------------|
| Broad substring HTML checks      | Add semantic selectors (classes/ids) and use structural assertions |
| Reusing cross-test DB state      | Factory creation per test |
| Silent new ignore in phpstan.neon| Document + add expiration |
| Adding new global fixtures       | Factory or targeted minimal fixture |
| Overly generic assertions        | Structural invariants (exact selector presence) |
| Duplicated locale tests          | Data providers |

---

## 16. Quick Command Cheatsheet (Copy/Paste)

Initial PHPStan (triage phase):
```
make stan-fast
```

Full run (default level=5):
```
make stan
```

Run at max level (optional):
```
make stan PHPSTAN_LEVEL=max
```

Annotate generics search:
```
grep -R "extends AbstractAdmin" src/Admin
grep -R "extends CRUDController" src/Controller | grep AdminController
```

Mutation (focused):
```
make infection FILTER=src/Security
```

Mutation (baseline wider):
```
make infection
```

Run a single functional test file:
```
docker compose exec -T fpm php vendor/bin/phpunit tests/Functional/EventUrlBehaviorTest.php
```

---

## 17. Extending This Document

If adding a new tooling layer:
1. Add section with purpose + canonical commands.
2. Reference any configuration files introduced.
3. Update test writing checklist if it changes developer ergonomics.

---

## 18. Assistance Behavior Summary (For AI Agent)

When asked to:
- “Add test”: propose factory-driven, locale-aware, structural assertions.
- “Fix failing test”: inspect isolation assumptions & factory correctness before altering assertions.
- “Improve performance”: suggest parallelization AFTER confirming no shared temp dirs / cross-test global writes.
- “Handle static analysis noise”: group by category, resolve in priority order, avoid knee-jerk ignores.

Never:
- Introduce broad `ignoreErrors` blocks.
- Replace structural assertions with loose substrings for convenience.
- Hardcode secrets or environment credentials.

---

### 18.1 Site-Aware Client Requirement (Sonata Page Multisite)

Context:
The project uses Sonata PageBundle with the "host path by locale" multisite strategy (`HostPathByLocaleSiteSelector`). Functional tests that hit any CMS / page-managed or localized route MUST wrap HTTP requests in a `SiteRequest`. Failing to do so leads to runtime exceptions:

`RuntimeException: You must configure runtime on your composer.json in order to use "Host path by locale" strategy`

Additionally, BrowserKit assertions (e.g. `assertResponseIsSuccessful`) rely on the internally registered WebTestCase client; creating an ad-hoc KernelBrowser without calling `static::createClient()` first causes:

`A client must be set to make assertions on it.`

Mandatory Pattern:
1. Always initialize a canonical Symfony test client via `static::createClient()` (this registers the instance with WebTestCase).
2. Immediately wrap or replace it with the `SiteAwareKernelBrowser` (which converts each outgoing `Request` into a `Sonata\PageBundle\Request\SiteRequest`).
3. Centralize this logic in a helper (`FixturesWebTestCase::initSiteAwareClient()`) so every functional test calls:
   ```
   protected function setUp(): void
   {
       parent::setUp();
       $this->initSiteAwareClient();
       $this->client = $this->client(); // retrieval accessor
   }
   ```
4. Never call `static::bootKernel()` + `new SiteAwareKernelBrowser(...)` directly in tests; that bypasses WebTestCase’s internal tracking and can break BrowserKitAssertionsTrait.

Rationale:
- Ensures locale/site resolution remains deterministic in randomized test order (Infection / `--order-by=random`).
- Avoids hidden dependencies on a prior test having created a client.
- Guarantees CMS routing & localized URL generation match production runtime expectations.
- Prevents noisy multisite runtime exceptions that mask real test intent.

Do NOT:
- Re-implement per-test manual kernel boots.
- Mix raw `KernelBrowser` and `SiteAwareKernelBrowser` instances in the same test class.
- Suppress the runtime exception via `try/catch`; fix the client initialization instead.

Future Hardening:
- Provide a lightweight single-site test selector override if future test slices need isolation without full multisite semantics.
- Add a health check test asserting that the first functional test in a fresh process can request `/` successfully (guards regressions in client bootstrap).

Documentation Touchpoints:
- This policy is mirrored in TESTING.md (Functional Tests section).
- Any change in multisite strategy (e.g. switching to a domain-based strategy) MUST update this section + TESTING.md + a Decision Log entry.


---

## 19. Open TODO Interlocks (Select Highlights)

| Task ID | Dependency / Precondition |
|---------|---------------------------|
| 31D     | Needed before deeper PHPStan cleanup |
| 31E     | Requires 31D complete |
| I       | Mutation baseline (some negative tests already in place) |
| E       | Finish substring purge (ProfileEditLocaleTest residual) |
| J       | Documentation expansion (will reference this CLAUDE.md) |

---

## 20. Contact Points (Conceptual)

If an entity type is unknown for a new Admin class:
- Temporarily use `<object>` generic but raise a task to replace it.
If an external service appears in a test:
- Replace with null / fake transport and document override.

---

End of CLAUDE.md
