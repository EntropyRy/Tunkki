# TESTING.md

## 14. Test Environment & APP_ENV=test Guard

All PHPUnit executions MUST run with APP_ENV=test. The Makefile enforces this automatically by injecting -e APP_ENV=test into every containerized PHP invocation.

Why this matters:
- Symfony only registers the special test service_container (test.service_container) in the test environment.
- DAMA\DoctrineTestBundle transaction isolation + Foundry story loading depend on test kernel boot.
- Running with APP_ENV=dev (or omitted) caused LogicException errors when calling static::getContainer() and can mask real test failures.

Enforced Guard:
A hard guard now exists in tests/bootstrap.php:
- On mismatch (APP_ENV not exactly "test"), the bootstrap throws a RuntimeException and prints a FATAL hint to STDERR.
- This fails fast instead of producing cascading unclear errors later in the suite.

Canonical Ways To Run Tests:
- Full suite: make test
- Functional only: make test-functional
- Single file:
  make test PHP_EXEC="$(docker compose exec -T -e APP_ENV=test fpm php)" FILTER= (not needed unless adapting)
  or directly (explicit env):
  docker compose exec -T -e APP_ENV=test fpm php vendor/bin/phpunit tests/Functional/EventScenariosTest.php

Common Mistake (Do NOT do this):
  docker compose exec -T fpm php vendor/bin/phpunit tests/Functional/EventScenariosTest.php
(ENV not passed → APP_ENV defaults to dev → bootstrap guard aborts.)

If You Intentionally Need a Different Env (rare):
- Create a separate, explicit task/RFC first. Do not bypass the guard ad hoc.

CI Implication:
- Any CI pipeline runner invoking phpunit must mirror the Makefile pattern (or just call make test) to avoid divergence.

Troubleshooting Checklist When Suite Fails Immediately:
1. Confirm the first stderr lines don’t include "[bootstrap] FATAL: Expected APP_ENV=test".
2. Run `make doctor` to ensure container services are healthy.
3. Re-run a single test via `make test-functional` to verify isolation.

This section formalizes policy so future contributors don’t reintroduce silent non-test environment runs.

## 15. Factory Usage & Patterns (Expanded)

This section consolidates and expands prior guidance (see original Section 3) with the finalized conventions now that factory adoption (Task #9) is complete.

Core Principles:
- Every new functional/integration test that needs entities must use a factory (no direct reliance on legacy fixtures).
- Keep each test’s data minimal and explicit; avoid “kitchen sink” entity graphs.
- Prefer semantic state methods over ad hoc inline attribute arrays.

Factory Directory:
- Location: `src/Factory/*Factory.php`
- Each factory extends `PersistentObjectFactory<Entity>`
- Custom states are additive and composable (e.g. `EventFactory::new()->published()->ticketed()->create()`)

Common EventFactory States (current):
- `published()` – ensures published & publishDate in the (recent) past
- `unpublished()` / `draft()` – sets `published=false` and forward-shifts publishDate
- `finished()` / `past()` – sets eventDate in the past (already occurred)
- `pastUnpublished()` – composite past + unpublished draft
- `external()` / `externalEvent()` – uses an external URL that should bypass internal slug routing
- `ticketed()` / `ticketedBasic()` – enables ticketing attributes
- `multiday()` – sets multiday range and until date
- `withBackgroundEffect()` – attaches deterministic effect JSON config

When to Add a New State:
- A pattern appears in ≥2 test classes OR a single test reads unclearly without a semantic label.
- Prefer naming from a domain perspective (`pastUnpublished()`) over technical attribute lists.

Inline Overrides:
- Use only for IDs/slugs/names you must assert against:
  ```
  EventFactory::new()
      ->published()
      ->create(['url' => 'router-case-slug', 'name' => 'Router Case']);
  ```

Associations:
- If an association (Member ↔ User) is mandatory for the behavior under test and the factory does not auto-provide it, add an association-creating state (e.g. `withUser()`).
- For optional relations, create them explicitly inside the test for clarity.

Avoid:
- Chaining large numbers of low-signal inline overrides.
- Creating broad “do-everything” states (anti-pattern: `fullyLoaded()`).

Refactoring Legacy Tests:
- Replace repository lookups against static slugs with per-test factory creation.
- Remove helper methods like `getEventBySlug()` once all tests create their own events.

Mutation/Extensibility Note:
- If future domain refactors move invariants into domain services/value objects, migrate complex state logic there and keep factories thin.

## 16. Assertion Modernization (Selector & Structural Focus)

Goals:
- High-signal, stable assertions; minimal brittle substring reliance.
- Use Symfony’s WebTestCase assertion helpers where possible.

Preferred Patterns:
- Presence: `assertSelectorExists('form[name="member"]')`
- Text: `assertSelectorTextContains('h1', 'Profile')`
- Redirect: `assertResponseRedirects('/en/login')`
- Status: `assertResponseIsSuccessful()`, `assertResponseStatusCodeSame(403)`

Migration Examples:
Old:
```
$this->assertStringContainsString('name="member[email]"', $html);
```
New:
```
$this->assertGreaterThan(0, $crawler->filter('input[name="member[email]"]')->count());
```

Error Feedback:
- Prefer structural hooks in templates (e.g. `<div class="form-error" data-test="password-mismatch">`) so tests assert `assertSelectorExists('[data-test="password-mismatch"]')`.
- If no hooks exist, add minimal `data-test` attributes rather than relying on translated text.

Audit Strategy (Task E):
1. Grep for `assertStringContainsString(` and `strpos(` in `tests/`.
2. Whitelist cases where raw HTML substring is acceptable (e.g. verifying inline JSON chunk).
3. Convert the rest to selector or attribute-based checks.
4. Track residual legacy cases in `todo.md` until eliminated.

Heuristics for Acceptable Substring Use:
- Verbatim JSON blob presence (until a better structural exposure exists).
- External redirect URL comparison when headers are already asserted.

## 17. Custom Browser (SiteAwareKernelBrowser) – Final Decision

Status: FINALIZED (Task #11 closed)

Mandate:
- `SiteAwareKernelBrowser` is the REQUIRED client for all functional tests.
- No further evaluation or deprecation experiments are pending.

Why It Remains Required:
- Guarantees `Sonata\PageBundle\Request\SiteRequest` wrapping (multi-site & locale context).
- Normalizes host, base URL, path info, and avoids subtle double-slash / snapshot resolution discrepancies.
- Ensures parity between test routing and production site-aware routing (especially for bilingual + site-prefixed paths).

Prohibited:
- Direct usage of `static::createClient()` in functional tests.
- Instantiating a new KernelBrowser or SiteAwareKernelBrowser manually, or duplicating request normalization logic; always initialize via the base helper and use the shared `$this->client`.

Allowed Exception (Explicit Only):
- Integration tests that do NOT exercise HTTP routing (they should not use any browser).
- Future specialized smoke tests that explicitly prove wrapper redundancy (must be run outside normal CI and documented here before changing policy).

Required Pattern:
```
protected function setUp(): void
{
    parent::setUp();
    // Always initialize the canonical site-aware client via the base helper.
    // This registers the client with WebTestCase and wraps outgoing requests
    // as Sonata\PageBundle\Request\SiteRequest without overriding baseUrl/pathInfo.
    $this->initSiteAwareClient();
    // Use $this->client (magic accessor) or $this->client() accessor from FixturesWebTestCase
    // for subsequent requests/assertions.
}
```

Trait Interaction:
- Login helpers MUST use the existing `$this->client` (do not instantiate a new internal client).
- If a trait needs a client and none exists, it must fail fast with a descriptive assertion.

Maintenance Guidelines:
- Do not override `baseUrl`/`pathInfo`; let the Router compute them. The wrapper only sets locale based on path prefix and injects the session.
- Keep `SiteAwareKernelBrowser` minimal (only site wrapping + lightweight normalization).
- Add no domain logic; enhancements go to traits/utilities.
- If Sonata upgrades eliminate the need for explicit `SiteRequest` wrapping, initiate a new evaluation task (create a “Browser Simplification RFC” section below and mark this mandate as “Under Review”).

Audit Checklist (Executed Before Finalization):
- Locale routing tests passed under wrapper-only mode.
- Event + profile bilingual paths resolved identically in production and test environments.
- Snapshot/page resolution depended on wrapped request attributes (removal caused mismatches).

End State:
- This section supersedes any earlier “transitional” language elsewhere in the document (see Section 5).

### CMS Baseline Policy (Sonata Page) — Once-per-run (Foundry Story)

Context:
- Functional tests rely on a minimal CMS baseline for Sonata PageBundle to resolve the homepages at / (Finnish) and /en/ (English).
- Seeding model: CmsBaselineStory is loaded once per test run in tests/bootstrap.php. It seeds exactly two Sites (fi default, en with /en) and required pages (root "/", events, join, announcements, stream).
- The base test class method ensureCmsBaseline() is a fast health-check only; it MUST NOT create, normalize, prune, or publish.

Snapshot Publication in Tests:
- In the test environment, sonata_page.direct_publication: true does not publish or enable snapshots during tests. The test bootstrap creates snapshots and force-enables them (page__snapshot.enabled=1) to satisfy Sonata DynamicRouter resolution.

## Parallel test execution (ParaTest)

Important: default to parallel execution (USE_PARALLEL=1). The CMS baseline seeding in tests is now idempotent and safe under parallelism; use serial runs only as an opt‑out for debugging flakiness or to bisect failures.

Guidance:
- When running in parallel, ensure per-process DBs (dbname suffix _test{TEST_TOKEN}) are active.
- Consider excluding tests that seed/normalize the CMS baseline (Sites/Pages) via an exclude-group in experimental parallel runs.
- Prefer serial execution for the Functional suite to avoid nondeterministic site/page duplication during baseline seeding.

- The Makefile runs tests in parallel when ParaTest is available. The default targets auto-detect CPU count and use that for the number of processes:
  - Full suite: `make test`
  - Unit only: `make test-unit`
  - Functional only: `make test-functional`
- To disable parallelization, set `USE_PARALLEL=0`:
  - `USE_PARALLEL=0 make test`
- To run ParaTest directly with an explicit process count:
  - `docker compose exec -T fpm php vendor/bin/paratest -c phpunit.dist.xml -p 4`
Diagnostics: `make test-parallel-debug` prints detected CPU count and the exact ParaTest command it would run. ORM lifecycle guardrails: set env flags to surface root-cause issues — `FAIL_ON_CLOSED_ENTITY_MANAGER=1`, `FAIL_ON_UNINITIALIZED_PROXIES=1`, optional growth logging `LOG_ENTITY_GROWTH=1` (works in both serial and parallel).
- Notes:
  - Database isolation: tests use transactional isolation; parallel runs are supported. If you observe unexpected flakiness, re-run serially (`USE_PARALLEL=0`) and report the case.
  - CMS baseline seeding is idempotent; it’s safe under parallel runs. If your test needs additional locales/sites, set them up within that test and avoid mutating the global baseline.
- Test bootstrap seeds Sites + root Pages, creates snapshots, and force-enables them for routing. The sonata_page.direct_publication: true setting does not publish snapshots in tests.
- Practical effect: After baseline seeding and snapshot enablement in bootstrap, requests to / and /en/ return 200.

Expectations for Tests:
- Do not attempt to publish snapshots in functional tests.
- Hitting / and /en/ must succeed with HTTP 200.
- Announcements page technical aliases remain unchanged: _page_alias_announcements_fi and _page_alias_announcements_en. Functional tests assert these aliases; do not rename them.

Event list gating rules (frontend):
- Announcements page:
  - Lists only public announcement events.
  - Repository call: findPublicEventsByType('announcement') which requires published=true AND publishDate <= now.
- Events page:
  - Active member (logged-in and Member::getIsActiveMember() === true): lists all events except announcements via findAllByNotType('announcement') — no publish gating is applied.
  - Others (anonymous or inactive members): lists only public events via findPublicEventsByNotType('announcement') — requires published=true AND publishDate <= now.
- Templates must not perform additional visibility gating; repository filtering is authoritative.

Test guidance:
- Anonymous/public view assertions must only expect events created with EventFactory::new()->published() and a publishDate in the past; draft or future-publishDate items must not appear.
- Active member view assertions should log in a user whose Member::getIsActiveMember() is true and may include both draft/unpublished and future-publishDate items (type != 'announcement').
- Prefer EventFactory states (published(), unpublished()/draft()) and explicit publishDate overrides to exercise boundary cases deterministically.
- Type comparisons for "announcement" are case-insensitive in repositories to avoid 'Announcement' vs 'announcement' inconsistencies.

Example health check for both locales:
```/dev/null/CmsBaselineExample.md#L1-10
$client->request('GET', '/');
self::assertResponseIsSuccessful();

$client->request('GET', '/en/');
self::assertResponseIsSuccessful();
```

Notes:
- If a test modifies Sites/Pages, it must restore a resolvable homepage state or limit such modifications to isolated unit/integration tests instead of functional routing tests.


### BrowserKit Request Guidance (Reinforced)

Helper-based seed examples (prefer these helpers in FixturesWebTestCase to standardize the first request/redirect follow-up):
```/dev/null/BrowserKitSeedingExamples.php#L1-12
// Seed homepage and follow a single redirect if present
$this->seedClientHome('en');

// Or seed the login page (locale-aware) and follow a single redirect if present
$this->seedLoginPage('fi');
```

Rule:
- Initialize the canonical site-aware client in setUp via `$this->initSiteAwareClient()` and use `$this->client` or `$this->client()` thereafter (do not instantiate a new KernelBrowser manually).
- Before any crawler-based assertions, seed a simple GET using helpers like `seedClientHome('en'|'fi')` or `seedLoginPage('en'|'fi')` to standardize the initial request and follow a single redirect if present.
- After any programmatic `loginUser(...)` in functional tests, call `stabilizeSessionAfterLogin()` to persist the serialized security token into the session so subsequent SiteRequest-wrapped requests remain authenticated.
- Always issue a request (or follow a redirect) before accessing $client->getRequest(), $client->getResponse(), or using selector assertions that depend on an active crawler. Prefer the helpers provided by FixturesWebTestCase: seedClientHome('en'|'fi') and seedLoginPage('en'|'fi') to standardize first request and redirect following.

Pattern:
- Perform an initial GET and assert status.
- Submit forms and then follow redirects if present before making crawler-based assertions.

Example:
```/dev/null/BrowserKitGuidance.php#L1-20
$crawler = $client->request('GET', '/login');
self::assertResponseIsSuccessful();

$form = $crawler->filter('form')->first()->form([
    '_username' => 'user@example.com',
    '_password' => 'wrongpass',
]);
$client->submit($form);

if ($client->getResponse()->isRedirect()) {
    $client->followRedirect();
}

self::assertSelectorExists('form input[name="_username"]');
```

Common mistakes to avoid:
- Calling $client->getRequest() or $client->getResponse() before any $client->request()/followRedirect() has been executed.
- Asserting on the crawler without first performing a request.


## 18. Form Validation & Negative Path Coverage

Status:
- Member registration form now covered for:
  - Invalid email
  - Mismatched passwords
  - Too-short password
  - Duplicate email
- Password change form covered for mismatch + success path.
- Pending:
  - Artist signup negative cases ( missing required field / invalid format )
  - Event creation/edit validation (if exposed in testable UI)
  - Unverified email restriction test (if domain rule enforced for specific actions)
  - Session invalidation test (login A → logout → login B)

Conventions:
- Treat each validation dimension as its own test method.
- Use consistent naming: `test<Context>Rejects<Condition>()`.
- Where feasible, assert specific error container selectors instead of raw text.

## 19. Checklist for New Functional Tests

Before writing:
[ ] Can this be a unit or integration test instead?
[ ] Are needed factory states present (if not, add them)?
[ ] Is there a structural selector I can assert instead of generic substrings?
[ ] Are locale or role variations better expressed via a data provider?

After writing:
[ ] No reliance on global fixtures (except intentionally persistent base structures like Sites/Pages if still unavoidable).
[ ] Assertions use helpers or selectors (no broad `assertStringContainsString` on entire HTML unless justified).
[ ] Redirect chain minimized/explicit.
[ ] Added any new state methods to the appropriate factory, not inline duplicated.


Testing conventions for the Symfony application.
Status: Initial draft (Phase 0/1 bootstrap). This document satisfies task #3 in `todo.md` (document test categories) and seeds several future tasks (13, 16, 19, 23, 24, 25, etc.).

## 20. Immutable Timestamp & Clock Policy

Rationale:
Deterministic, mutation‑resistant temporal logic requires a single seam for "now". Direct usage of new DateTime()/new DateTimeImmutable() in services and controllers makes boundary tests brittle and mutation testing less effective (mutants that invert time comparisons frequently survive when time is captured inconsistently).

Policy Summary:
- All new time‑dependent domain/service logic obtains "now" from the Clock abstraction (App\Time\ClockInterface or Symfony\Contracts\Service\ClockInterface if migrated).
- Direct instantiation is allowed ONLY in:
  * Entity lifecycle callbacks (prePersist/preUpdate) where setting createdAt/updatedAt
  * Domain factories (src/Factory) for default seed values
  * Foundry Stories (immutable baseline seeding)
  * Doctrine migrations
  * Tests (arranging controlled timestamps)
- Everything else (Controller, Service, Repository, Security, Domain service classes) must inject the clock.

Phase 1 Enforcement (Implemented):
- CI shell script ci/check_datetime.sh scans disallowed layers for new DateTime( and new DateTimeImmutable( usage.
- Makefile target: make lint-datetime (invoked manually or added to CI pipeline).

Phase 2 (Planned):
- Add a PHPStan custom rule disallowing direct DateTime instantiation outside allowlists, producing richer context.

Refactoring Guidelines:
1. Inject ClockInterface:
   public function __construct(private ClockInterface $clock) {}
2. Capture a base instant once per code path:
   $now = $this->clock->now();
3. Derive offsets from that base:
   $windowEnd = $now->modify('+2 hours');
4. Avoid multiple "now" calls for related comparisons (prevents sneaky race conditions).
5. For test control:
   - Use the MutableClock/FixedClock test override (defined in services when@test) to freeze or advance time.
   - In mutation tests, adjust the mutable clock to exercise both sides of temporal branches.

Construction Patterns:
Before (forbidden in service):
  $expiresAt = new \DateTimeImmutable('+15 minutes');

After:
  $expiresAt = $this->clock->now()->modify('+15 minutes');

Entity Lifecycle Example:
  #[ORM\PrePersist]
  public function stamp(): void {
      $now = new \DateTimeImmutable();
      $this->createdAt = $now;
      $this->updatedAt = $now;
  }

Test Example with Mutable Clock:
  $clock->setNow(new \DateTimeImmutable('2025-10-02T12:00:00Z'));
  // Execute first branch
  $clock->setNow($clock->now()->modify('+2 hours'));
  // Execute second branch

Exception Documentation:
If a value object or external library wrapper must internally construct DateTimeImmutable for normalization (and cannot receive a clock), annotate with:
  // CLOCK-EXCEPTION: Normalizing RFC3339 input; not a "current time" semantic
and add a follow‑up task if broader redesign is feasible.

Review Checklist (Code Review Gate):
[ ] No new direct new DateTime*( in disallowed layers
[ ] Single now() capture per logical branch
[ ] Temporal comparisons covered by at least one negative/boundary test
[ ] Mutation survivors (if any) for temporal logic have follow‑up tasks

Violation Handling:
- If the CI script reports a violation, refactor to inject the clock. If genuinely unavoidable, move the code to an allowlisted layer or add a narrowly scoped exception with TODO and expiry date.

End State Goal:
Zero non-allowlisted "live time" instantiations in Controllers/Services/Repositories/Security enabling high signal from temporal mutation testing and stable deterministic functional tests.


## 1. Test Categories & Layer Definition

We maintain three primary test layers. Choose the lowest layer that gives meaningful confidence.

1. Unit Tests
   - Namespace: `App\Tests\Unit\...`
   - No kernel boot. Pure PHP objects only (value objects, domain services, helpers).
   - No database, filesystem, HTTP, container, or external bundles.
   - Fast feedback; should run in < 100ms per file in aggregate.
   - Use PHPUnit mocks (or phpspec-style doubles) sparingly; prefer real collaborators when simple.

2. Integration Tests
   - Namespace: `App\Tests\Integration\...`
   - Boot the Symfony kernel, access the container, call real services.
   - Avoid HTTP layer (do not use `KernelBrowser` unless specifically validating a request/response transformer).
   - Use real database connection but keep data local to each test (prefer factories over fixtures).
   - Examples: Repository queries, service wiring, data mappers, security voters, mailer logic (sans external transport calls).

3. Functional (End-to-End Slice) Tests
   - Namespace: `App\Tests\Functional\...`
   - Exercise HTTP entrypoints with `KernelBrowser` (or transitional `SiteAwareKernelBrowser` while still needed).
   - Assert routing, controllers, templates, translations, forms, security (firewalls/roles), page structure.
   - Keep scenarios focused; avoid multi-purpose “mega tests”.
   - Use factories for test-specific data. Do not rely on global cross-test mutable state.

(Planned) Slow / Extended / Nightly Suite (future)
   - Potential group for non-transactional DB truncation validation (see isolation model below).
   - Mutation testing, long-running property-based tests, or snapshot verifications.


## 2. Naming & Structure Conventions

Files:
- One test class per primary behavior cluster. Avoid > ~300 lines per class; split logically (e.g. `Login/SuccessfulLoginTest.php`, `Login/InvalidCredentialsTest.php`).
- Class suffix: `*Test` (PHPUnit default discovery).
- Method names:
  - Prefer `test<Scenario>` (e.g. `testUserCanAuthenticateWithValidCredentials`).
  - For data providers: `test<Scenario>_<CaseDescription>` optional, or rely on dataset names.

Recommended Pattern (descriptive):
```
public function testRedirectsToLoginWhenUnauthenticated(): void
public function testAdminAccessDeniedToPlainUser(): void
```

Optional BDD style acceptable if consistent:
```
public function testGivenInactiveMember_whenAccessingDashboard_thenRedirectsToActivation(): void
```

Do not:
- Use vague names (`testWorkflow`, `testItWorks`).
- Combine unrelated assertions in one method (split into separate tests).


## 3. Data Creation & Factories

Primary mechanism: Zenstruck Foundry (`src/Factory/*Factory.php`).

Guidelines:
- Create only the minimal entities required for a test.
- Use states (e.g. `UserFactory::new()->create()`, `EventFactory::new()->unpublished()->create()`).
- Avoid depending on large canonical fixture datasets inside new or refactored tests.
- If a needed state doesn’t exist yet, add a concise state method to the factory.
- Keep state method names business-oriented (`published()`, `signupEnabled()`, `finished()`, `active()`, `english()`).

Current Limitation:
- We’re on a Foundry version exposing `PersistentObjectFactory` with `addState()`; an extended custom immutable state trait (replacing `addState`) is planned (see TODO tasks #8/#9). For now, use the existing states and plan to migrate once the trait is introduced.

Associations:
- Factories may auto-create related entities (e.g. `MemberFactory` creates a `User` unless `withoutUser()` is chained).
- For clarity in tests, explicitly assert relevant associations if they influence logic (e.g. ensure a `User` has `ROLE_ADMIN` before asserting admin pages).

Avoid:
- Direct `new Entity()` + manual persisting inside functional tests unless a factory is missing; if missing, add a factory/state instead.


## 4. Database Isolation Model

Mechanism: Transactional isolation via DAMADoctrineTestBundle (DAMA).
- Each test runs inside a transaction rolled back afterward (fast and clean).
- Avoid manual transaction management in tests (can break isolation).
- DO NOT depend on a previous test’s database mutations.
- Test requiring visibility across commits must be redesigned or (rarely) placed in a special non-transactional group (not yet defined).

Nightly Consideration (future):
- Add a non-transactional truncation-based run to detect issues masked by transactional rollback (e.g. triggers, cascading behavior assumptions).


## 5. HTTP Client Usage

Mandated Client (Final Decision):
- ALL functional/end-to-end tests MUST use `SiteAwareKernelBrowser` and initialize it via `$this->initSiteAwareClient()` in `setUp()`. Do not instantiate the browser directly; use `$this->client` or the `$this->client()` accessor provided by the base test.
- Rationale: Sonata Page multi-site + locale resolution requires wrapping each Symfony `Request` in `Sonata\PageBundle\Request\SiteRequest`. Using the plain `KernelBrowser` causes subtle routing and site context inconsistencies (locale prefix handling, site-aware route generation, and snapshot/page resolution).
- Note: In tests, sonata_page.direct_publication: true has no effect on snapshot publication. Snapshots are created and force-enabled during test bootstrap to satisfy routing; direct publication only takes effect when pages are created or edited via Sonata PageAdmin in a real runtime.

Enforced Rules:
- Instantiate the client exactly once per test (or per `setUp`) via:
  ```
  $this->client = new SiteAwareKernelBrowser(static::bootKernel());
  $this->client->setServerParameter('HTTP_HOST', 'localhost');
  ```
- Never call `static::createClient()` in functional tests (reserved for lower-level experiments or future deprecation path updates).
- Do not subclass or wrap `SiteAwareKernelBrowser` further; keep all test-specific behavior (e.g. auth helpers) in traits.
- Host header must always be set to a deterministic value (`localhost`) to avoid environment variability.

Justification Summary:
- Ensures consistent site context injection.
- Normalizes request URI sanitization (double-slash trimming & base URL handling).
- Prevents divergence between tests and production site selection logic.

Future Reassessment:
- This mandate stands until:
  1. Site context logic moves to kernel/request listeners independent of the browser wrapper, AND
  2. A controlled A/B branch proves parity over a representative locale + routing subset.

If both conditions are met, this section will be revised and Task #11/#53 retired.


## 6. Authentication & Login Helpers (Planned)

Upcoming task (#19):
- Introduce a trait or helper methods:
  - `loginUserEntity(User $user): KernelBrowser`
  - `loginAsEmail(string $email): KernelBrowser`
  - `loginAsRole(string $role): KernelBrowser` (will internally create a user + assign role via factory)
- For tests not validating the raw form submission flow, prefer programmatic login (`$client->loginUser($user)`).

Form Login Tests:
- Only a small subset should exercise `/login` end-to-end (CSRF, invalid credentials, success path).
- Other tests should skip the form for performance and clarity.


## 7. Assertions & DOM Inspection

Preferred Assertions (replace raw DOM scraping):
- `assertResponseIsSuccessful()`
- `assertResponseStatusCodeSame(…)`
- `assertResponseRedirects('/expected')`
- `assertSelectorExists('css-selector')`
- `assertSelectorTextContains('h1', 'Dashboard')`

Anti-patterns to eliminate (tasks #15/#16/#23):
- Manual content substring fallback assertions like:
  - `str_contains(strtolower($content), 'dashboard') || !empty($content)`
- Redirect chasing loops—limit to meaningful checks; use `$client->followRedirect()`.

Write high-signal assertions:
- Assert structural presence (e.g. `main[data-test="dashboard"]`) where possible.
- Avoid brittle full HTML equality; focus on semantics.


## 8. Environment Variables in Tests

Current Violation Noted:
- `putenv('TEST_DEBUG_LOGIN=1')` in `LoginTest::setUp()` (task #24).

Policy:
- Do not mutate global environment variables inside tests.
- If a test requires a feature toggle, prefer:
  1. Test-only service decorator.
  2. Parameter injection via `kernel.test.yaml`.
  3. A test-scoped runtime configuration file loaded before kernel boot (last resort).

Action:
- Remove remaining `putenv()` usages during the LoginTest split (task #13 & #24).


## 9. Large Scenario Test Splitting

`LoginTest` Example (task #13):
- Original large multi-purpose file will be split into:
  - `Login/SuccessfulLoginTest.php`
  - `Login/InvalidCredentialsTest.php`
  - `Login/AdminAccessTest.php`
  - `Login/SuperAdminAccessTest.php`
  - `Login/CsrfProtectionTest.php` (optional)
- Each test file exercises a single responsibility and sets up only necessary data.

General Rule:
- If a test method requires conditionals or loops to “reach” the final assertion, it’s likely too broad.


## 10. Locale / Internationalization Testing

Planned (task #14 & #22):
- Use data providers for bilingual or multi-locale route behaviors:
  ```
  /**
   * @dataProvider provideLocaleRoutes
   */
  public function testLocalizedRouteResolves(string $locale, string $expectedTitle): void
  ```
- Do not copy/paste identical flows for each locale.

For dynamic copy assertions:
- Assert key structural markers or translation keys output (if stable).


## 11. Security & Role Testing

Minimum Coverage Targets (tasks #20, #28):
- Unauthenticated access to protected routes -> redirect/login.
- Authenticated user lacking role -> 403 (or redirect) as per policy.
- Admin/super-admin reach dashboards.
- CSRF invalidation for forms requiring tokens.

When adding role-based tests:
- Use factories to create users with specific role arrays.
- Keep role strings explicit (`['ROLE_ADMIN']`) to avoid drift.


## 12. Time & Determinism

## 12a. Service Overrides (Consolidated when@test Block)

Status: IMPLEMENTED (2025-10-02). Replaces previous separate files under `config/packages/test/`.

Overview:
The test-only service overrides (deterministic clock + null mailer transport) are now declared inside a single `when@test:` block at the bottom of `config/services.yaml`. The former `config/packages/test/clock.yaml` and `config/packages/test/mailer.yaml` files and their containing `test/` directory were removed to enforce the single‑file override convention (see CLAUDE.md §12).

Current Overrides (in services.yaml):
when@test:
  framework:
    mailer:
      dsn: "null://null"
  parameters:
    test.fixed_datetime: "2025-01-01T12:00:00+00:00"
  services:
    # Primary mutable clock used for all time-dependent logic in tests
    App\Time\ClockInterface:
      class: App\Time\MutableClock
      arguments: ["%test.fixed_datetime%"]
      public: true

    # Direct alias (semantic name) for convenience in some tests
    test.mutable_clock:
      alias: App\Time\ClockInterface
      public: true

    # Backwards-compatible alias (legacy references still work)
    test.fixed_clock:
      alias: App\Time\ClockInterface
      public: true

Rationale:
- Centralizes environment-specific wiring (clock, mailer) to avoid config sprawl.
- Makes audit of test-only behavior trivial (one file diff).
- Aligns with project policy: “Do NOT create new config directories for environment overrides.”

Usage Guidelines:
1. Always inject `App\Time\ClockInterface`; never scatter `new DateTimeImmutable()` in services.
2. For temporal boundary tests, use the TimeTravelTrait (see below) to freeze or advance time instead of replacing the service manually.
3. Manual (fallback) mutation pattern if absolutely needed:
   $clock = static::getContainer()->get(App\Time\ClockInterface::class);
   assert($clock instanceof \App\Time\MutableClock);
   $clock->setNow('2030-12-31T23:59:59+00:00');
4. Quick reference to current “now”:
   $now = static::getContainer()->get(App\Time\ClockInterface::class)->now();
5. Legacy usages of 'test.fixed_clock' still work (alias) but prefer the interface.

Adding New Overrides:
- Add them inside the existing when@test block (do NOT create a new YAML file).
- Document the addition in TESTING.md (this section) + Decision Log in todo.md.
- Keep them minimal & deterministic (no external I/O, no randomness unless seeded).

Anti‑Patterns (Avoid):
- Introducing a second when@test block in another file (harder diff readability).
- Overriding core services in a way that modifies production semantics (e.g., replacing security voters) unless explicitly required & documented.
- Relying on environment variables mutated at runtime instead of service overrides.

Future Improvements:
- (DONE) MutableClock implemented (advanceSeconds/Minutes/Hours/Days + relative advance).
- Add a messenger override (force sync) if async transport introduced.
- Provide fake notifier / external API stubs under when@test once such integrations land.
- Consider adding a domain-specific TimeWindow helper once multiple services replicate window logic.

Verification Checklist (post-consolidation):
[ ] No remaining references to removed files (grep -R "packages/test/clock.yaml" / "test.fixed_clock" id patterns).
[ ] All tests still green after consolidation.
[ ] Documented in todo.md Decision Log (DONE).
[ ] This section present in TESTING.md (current edit).

Review / Maintenance:
- On adding a new override, append a dated bullet below:
  YYYY-MM-DD: Added X override (reason). No production impact.

Dated Entries:
- 2025-10-02: Consolidated fixed clock + null mailer overrides under when@test (initial entry).
- 2025-10-02: Replaced FixedClock with MutableClock + added aliases (test.mutable_clock, test.fixed_clock) and TimeTravelTrait.


Current Issue (future tasks #43):
- Tests using real `new \DateTimeImmutable()` can become flaky (boundary conditions).

Policy (incremental):
- For pure domain logic sensitive to time, abstract time behind a `ClockInterface` (Symfony >= 6 provides one).
- Use a fixed or controllable clock in tests.
- For now, factories produce relative times (e.g. `+7 days`); acceptable but may evolve to a central `TestClock`.


## 12b. TimeTravelTrait (Temporal Test Utilities)

Purpose:
Provides expressive, fluent helpers for manipulating the simulated clock in tests without re‑binding services manually. Supports freezing to an absolute instant and advancing (or rewinding) using relative modifiers. This enables deterministic coverage for temporal boundary conditions (publish windows, signup cutoffs, expirations) and improves mutation testing effectiveness (kills off-by-one and inverted comparison mutants).

Location:
tests/Support/TimeTravelTrait.php

Automatic Upgrade Behavior:
If the container is currently using a FixedClock or AppClock for App\Time\ClockInterface, the trait will transparently replace it with a MutableClock (bound in when@test) on first time-travel operation so subsequent calls are always supported.

Provided Methods:
- now(): DateTimeImmutable
- freeze(string|DateTimeImmutable $instant): static
- travelTo(string|DateTimeImmutable $instant): static (alias of freeze)
- travel(string $relativeModifier): static (e.g. '+15 minutes', '-2 days', 'next monday')
- travelSeconds(int $seconds): static
- travelMinutes(int $minutes): static
- travelHours(int $hours): static
- travelDays(int $days): static
- assertNowEquals(string|DateTimeImmutable $expected, string $message = ''): void (optional convenience)

Usage Example:
```php
use App\Tests\Support\TimeTravelTrait;
use App\Domain\EventPublicationDecider;

final class EventPublicationWindowTest extends WebTestCase
{
    use TimeTravelTrait;

    public function testEventTransitionsFromDraftToLiveAtBoundary(): void
    {
        $event = EventFactory::new()
            ->draft()
            ->create(['publishDate' => new \DateTimeImmutable('2025-01-01T12:00:00+00:00')]);

        $decider = static::getContainer()->get(EventPublicationDecider::class);

        $this->freeze('2025-01-01T11:59:59+00:00');
        self::assertFalse($decider->isPublished($event), 'One second before publish boundary should be draft.');

        $this->travelSeconds(1); // Now exactly at publish boundary
        self::assertTrue($decider->isPublished($event), 'Publish boundary instant should mark event live.');

        $this->travel('+2 hours');
        self::assertTrue($decider->isPublished($event), 'Event remains live after boundary passes.');
    }
}
```

Best Practices:
1. Use small, explicit jumps (seconds/minutes) for edge cases; use larger semantic jumps (days/hours) for broader scenarios.
2. Keep time shifts local to a single test; do not rely on test execution order.
3. Combine with factory states (e.g. signupWindowJustOpened) for clarity instead of large manual date arithmetic inline.
4. Pair boundary assertions (just before → at → just after) to maximize mutation detection.

Common Pitfalls (Avoid):
- Calling sleep() or usleep() to “wait” for time to pass (non-deterministic).
- Advancing time without also asserting the pre-condition first (missed branch coverage).
- Direct new DateTimeImmutable('now') inside temporal services (bypasses the clock abstraction and invalidates time travel).

When NOT to Use:
- Pure unit tests with manually constructed timestamps and no clock injection (prefer passing explicit DateTimeImmutable instances).
- Static value object logic that does not depend on the moving notion of now.

Extension Ideas (Future):
- Add travelUntil(callable $predicate, string $step = '+1 minute') for iterative window searches (only if needed).
- Add snapshot/restore stack for nested temporal contexts (rare; defer until justified).

Dated Entry (Documentation):
2025-10-02: Introduced TimeTravelTrait with automatic MutableClock upgrade and documented usage patterns.

## 13. Mutation & Coverage (Future Work)

Infection quickstart
- Purpose: establish a baseline MSI and drive tests from survivors.
- Command (inside container):
  - make infection FILTER=src/Security
  - Outputs:
    - Logs and HTML: build/infection/ (text, summary, html, badge)
    - Metrics: append results manually to metrics/mutation-baseline.md (Section 3 and 4)
- Notes:
  - Thresholds are 0 in infection.json.dist for exploratory runs.
  - Start with focused namespaces (src/Security, src/Repository) for speed.

Symfony script helpers (Make targets)
- make scripts-debug-baseline: Verifies FI/EN sites and key pages exist; flags duplicates and missing roots.
- make scripts-route-debug: Probes ChainRouter with a SiteRequest for “/” and “/en/” and prints which inner router matched.
- make scripts-seed-baseline: Runs the CmsBaselineStory seeding/normalization once and prints site/root status.

Usage policy
- Run from repository root; the make targets execute inside the Docker FPM container with APP_ENV=test.
- Use these helpers to debug routing and CMS baseline issues when functional tests fail.

Mutation Testing (task #31):
- Run `composer mutation` (Infection) once core refactors are stable.
- Record baseline Mutation Score Indicator (MSI) and drive test improvements from surviving mutants.

Coverage Gates (task #37):
- Do not enforce minimum coverage until noise is reduced (post factory migration & test splitting).
- Focus coverage additions on:
  - Domain invariants
  - Permission logic
  - URL/event state logic


## 14. Performance Guidelines

Keep functional tests lean:
- Only create entities needed for the assertion.
- Avoid loading large fixture graphs “just in case”.
- Consider grouping extremely slow tests under `@group slow` once identified (task #42).

Parallelization (task #34):
- Blocked until we confirm full per-test isolation (no shared filesystem artifacts, deterministic factories).
- When enabled, watch out for:
  - Static caches
  - Temp file collisions
  - Port bindings (none expected currently)


## 15. Adding a New Test (Quick Checklist)

1. Pick correct layer (Unit vs Integration vs Functional).
2. Decide if entity data needed:
   - Yes → Use relevant factory (add missing state if required).
3. Name test clearly (behavioral description).
4. For functional tests:
   - Create client (`static::createClient()` or transitional site-aware client).
   - Perform request.
   - Use high-level assertions (no manual scraping loops).
5. Clean up? Normally no—transaction rollback handles DB.
6. Avoid global env mutations.
7. Commit + ensure CI passes.

If something feels “heavy” or brittle, re-evaluate the layer choice or data setup strategy.


### 15.a 1:1 User↔Member Factory Policy (FACT4)

Context:
The strict 1:1 User↔Member relationship (owning side: User.member with NOT NULL + UNIQUE FK) previously caused intermittent integrity errors in tests (SQLSTATE 23000: member_id cannot be null) when a User row was flushed before its Member was linked. The root cause was a premature `UserFactory::create()` call inside `MemberFactory::afterInstantiate()` which performed an INSERT before the bidirectional linkage was established.

Stabilized Strategy (FACT4):
1. MemberFactory no longer sets a `user` association in `defaults()`.
2. In `afterInstantiate()`, if auto‑creation is enabled and no user was injected, a non‑persisted `new User()` instance is created manually (no flush).
3. Bidirectional link is established immediately (owning side first):
   - `$user->setMember($member);`
   - `$member->setUser($user);`
4. A `beforePersist` (or equivalent) hook persists the User so both entities enter the UnitOfWork together; Doctrine performs one flush where `member_id` is already populated.
5. The login/helper logic (Phase 3) now uses a factory‑only path (`createFreshPairFactoryOnly`) instead of a manual two‑phase member→user creation.
6. Legacy two‑phase fallback (`createFreshPairTwoPhaseSafe`) is deprecated and scheduled for removal after two consecutive green full-suite runs (Decision Log: FACT4).

Invariant (Must Always Hold):
- `MemberFactory::new()->create()` returns a Member whose `getUser()` is a User instance with a non-null ID after flush.
- `User->getMember()` strictly equals that Member instance.
- No code path creates a standalone User or Member intended for later pairing (avoid orphans entirely in tests).

Disabling Auto Creation:
- Use `MemberFactory::new()->withoutUser()` only for specific negative scenarios that intentionally require a Member without a User (rare). Such tests must document the reason.

Helper Impact:
- Phase 3 in `LoginHelperTrait` now calls `createFreshPairFactoryOnly()`.
- Role merging (if requested) happens after the factory flush in a single additional update (minimal overhead).
- Transitional debug logs (set `TEST_USER_CREATION_DEBUG=1`) show phases:
  - `afterInstantiate.user-linked`
  - `beforePersist.user-persisted`
  - `fresh-factory.*` (helper phases)

Migration / Cleanup Plan:
- Keep both paths (factory-only + deprecated two-phase) until stability proven.
- Remove the deprecated method & references after two consecutive green CI runs; record SHAs in Decision Log (FACT4 follow-up).

Micro-Test Recommendation:
Add a focused integration test:
```/dev/null/MemberFactoryMicroTest.php#L1-8
$memberProxy = MemberFactory::new()->create();
$member = $memberProxy;
self::assertNotNull($member->getId());
$user = $member->getUser();
self::assertInstanceOf(User::class, $user);
self::assertNotNull($user->getId());
self::assertSame($member, $user->getMember());
```
This guards against future regressions in factory hook ordering.

Troubleshooting Checklist:
- If integrity error reappears: enable `TEST_USER_CREATION_DEBUG=1`, re-run failing test, confirm ordering logs.
- Verify no reintroduction of a `UserFactory::create()` call inside MemberFactory hooks.
- Ensure no test manually flushes mid-construction (should rely on factory flush only).
- For BrowserKit errors like “request() must be called before accessing the request/response”, issue a request first (e.g. `$client->request('GET', '/')`) or follow a redirect with `$client->followRedirect()` before calling `$client->getRequest()`/`$client->getResponse()`.

Removal Criteria (Deprecated Path):
- Decision Log FACT4 follow-up states removal after two consecutive green runs + micro-test present.
- Once removed, grep for `createFreshPairTwoPhaseSafe` must return zero matches.

Rollback Strategy:
If a future library upgrade alters hook timing and causes early flush:
1. Temporarily re-enable the deprecated two-phase path.
2. Add a one-off explicit `$em->flush()` after persisting `Member` and before persisting `User` (only if strictly necessary).
3. Capture debug logs and add a new Decision Log entry detailing the anomaly.

By enforcing this policy, the suite maintains deterministic, atomic creation of authentication pairs and eliminates a historical flakiness source.

## 16. Transitional Legacy Notes

Until all legacy fixture dependent tests are migrated:
- (DEPRECATED – DECISION 2025-10-03) Previous reliance on canonical fixtures has been removed. Functional tests must assume an empty database (schema only) at start.
- All new and existing functional tests MUST create their own data via Foundry factories or explicit per-test setup. No global fixtures are loaded in the test environment.
- Tracking of former fixture migration tasks (#8/#9) is now closed; follow-up hardening tasks are recorded under the new "Fixture-Free Suite" section in `todo.md`.

`FixturesWebTestCase`:
- Enforces fixture-free policy: tests must not assume preloaded data. If a test still depends on a legacy slug/email, refactor it to create that entity inside the test or a local helper before assertions.
- Will evolve/retire once factory adoption reaches critical mass and canonical fixtures are minimized to only what is impractical to factory-create (if any).


## 17. Open Tasks Referenced Here

## 18. Pre-Commit Hook (Local Quality Gate)

A lightweight pre-commit hook is provided to catch obvious issues early (PHPStan fast pass + unit tests + optional style). This improves feedback speed and reduces CI churn.

Location:
.githooks/pre-commit

Enabling (one-time):
```
git config core.hooksPath .githooks
chmod +x .githooks/pre-commit
```

What it Runs (by default):
1. PHPStan fast subset (make stan-fast; defaults to level 5)
2. Unit test suite (make test-unit)
3. php-cs-fixer dry-run (if tool/vendor present)

Skip / Force Mechanisms:
- Skip via env var: SKIP_QA=1 git commit -m "msg"
- Skip via commit message token: add [skip qa] or [skip hooks]
- Force run even if no PHP/config changes detected: FORCE_QA=1 git commit -m "msg"

Change Detection:
The hook only triggers full checks if staged files match a PHP/config pattern (PHP files, composer.json/lock, phpstan.neon, phpunit.xml(.dist), symfony.lock). Otherwise it exits quickly unless FORCE_QA=1 is set.

Running Inside Docker:
If the docker compose service fpm exists, commands execute in the container (docker compose exec -T fpm ...). Otherwise they run on the host (expects matching PHP version).

Failure Behavior:
- Any failing step aborts the commit with a summary.
- You may still override intentionally (e.g. experimental WIP) via SKIP_QA=1, but this should be rare.

Extending the Hook (Guidelines):
- Keep it fast (< ~10s ideal). Avoid running the full functional suite.
- Prefer adding new Make targets (e.g. make stan-delta) rather than inlining long commands.
- If adding mutation or coverage steps, guard behind an opt-in env var (e.g. RUN_MUTATION_ON_PRECOMMIT=1).

Recommended Workflow:
1. Write / refactor code.
2. Stage changes.
3. Commit → hook runs (quick pass).
4. Push → CI executes full matrix (functional tests, mutation, extended PHPStan).

Troubleshooting:
- If the hook complains about missing vendor tools, run composer install (inside container if PHP version mismatch on host).
- Ensure executable bit retained (chmod +x .githooks/pre-commit) after checkout on some systems.

Rationale:
- Shifts detection of type / unit regressions left.
- Reduces flaky “fix after push” cycles.
- Encourages maintaining a fast unit + static subset.

Future Enhancements (tracked in todo.md / MT16 / MT30):
- Add stan-delta (changed files only).
- Optional infection diff mode (advisory).
- Auto-fix styling before commit (convert style dry-run into actual fix on staging area with a protected fallback).


| Task # | Summary (from todo.md) | Reflection in This Document |
|--------|------------------------|------------------------------|
| 3      | Document test categories | Sections 1 & whole file |
| 8/9    | Introduce factories & migrate | Sections 3 & 4 |
| 11     | Evaluate custom browser | Section 5 |
| 13     | Split LoginTest | Sections 6 & 9 |
| 14/22  | Locale tests data providers | Section 10 |
| 16/23  | Adopt WebTestAssertions, reduce verbose DOM crawling | Sections 7 & 15 |
| 19     | Login helper | Section 6 (planned) |
| 24     | Remove env var mutations | Section 8 |
| 28     | Security boundary assertions | Section 11 |
| 31/37  | Mutation & coverage gating | Section 13 |
| 34     | Parallel execution | Section 14 |
| 42     | Tag slow tests | Section 14 |
| 43     | Time control / clock abstraction | Section 12 |
| 48/49/50 | Documentation expansions | This file is initial baseline |
| 53     | Re-review custom infra | Sections 5 & 16 |


## 18. Evolution & Maintenance

Update Rules:
- When adding a new convention, append a concise rationale (why it exists).
- Do not remove historical context without summarizing it first.
- When a transitional constraint (e.g., reliance on `SiteAwareKernelBrowser`) is lifted, replace its section with a short deprecation note for 1–2 release cycles.


## 19. Definition of Done (Documentation Perspective)

This document considered “complete” for Phase 2 when:
- Login test splitting done and reflected here.
- Environment mutations removed and section updated to “Enforced”.
- Factory state trait (if added) documented.
- Test helper traits (login, locale) referenced with file paths.
- CI guidance (fast vs full commands) added.


## 20. Quick Reference Cheat Sheet (Copy-Paste Friendly)



- Profile durations per file → metrics/test-times.txt:
  make test-profile

- Run full suite in parallel (default):
  make test

- Run full suite serially (opt-out fallback):
  USE_PARALLEL=0 make test

Functional request:
```
$client = static::createClient();
$client->request('GET', '/en/dashboard');
self::assertResponseIsSuccessful();
self::assertSelectorTextContains('h1', 'Dashboard');
```

Entity via factory:
```
$user = UserFactory::new()->create()->object();
$event = EventFactory::new()->published()->create()->object();
```

Role-specific user:
```
$admin = UserFactory::new(['roles' => ['ROLE_ADMIN']])->create()->object();
$client->loginUser($admin);
```

Negative access assertion:
```
$client->request('GET', '/admin/dashboard');
self::assertResponseStatusCodeSame(403);
```

Data provider skeleton:
```
/**
 * @dataProvider provideLocales
 */
public function testHomepageLocalized(string $locale, string $expected): void
{
    $client = static::createClient();
    $client->request('GET', "/$locale/");
    self::assertResponseIsSuccessful();
    self::assertSelectorTextContains('title', $expected);
}

public static function provideLocales(): iterable
{
    yield ['en', 'Welcome'];
    yield ['fi', 'Tervetuloa'];
}
```


## 21. Pending Enhancements (Not Yet Implemented)

- Immutable custom factory state trait replacing raw `addState()` chain.
- Central `TestLoginHelper` & `TestLocaleHelper`.
- Clock abstraction & deterministic time provider.
- Mutation baseline recording & README/CI badge.
- Parallelization opt-in docs.


---

Revision 2025-10-02 (Initial draft created).

## 22. How to Write a New Test (Detailed Guide)

This section expands the quick checklist (Section 15) into a step-by-step, opinionated workflow aligned with current guardrails.

### 22.1 Decide the Test Layer
Use the cheapest viable layer:
- Unit: Pure logic, no container, no DB. (Preferred for value objects, calculators, deciders.)
- Functional: Needs HTTP kernel (routing, controllers, templating, security integration).
- Integration: Cross-service wiring, Doctrine queries exercising mappings (rare; often can be unit + functional split).

Heuristics:
- If you only assert return values of a service and can new-up dependencies (or provide test doubles), choose Unit.
- If locale routing, security roles, forms, or Twig rendering are involved, choose Functional.
- If you are validating a complex Doctrine query or repository logic that depends on real mapping behavior, Integration (or focused functional) is acceptable.

### 22.2 Prepare Data (Factories Only)
- Start with the most specific existing factory state (e.g. `EventFactory::new()->published()`).
- Compose additional attributes inline only if not represented by a named state.
- If you need a new semantic combination more than once → add a named state to the factory (and document it in the Factory State Catalog below).
- Avoid creating more entities than the assertions require.

### 22.3 Temporal Considerations
If logic depends on time:
- Use `TimeTravelTrait` (functional/integration) or inject the `ClockInterface` (unit).
- For boundary tests: assert before, at, and after the boundary to kill condition mutants.
- Never rely on real time drifting during a test.

### 22.4 Authentication & Roles
- Use a factory to create the user with explicit roles: `UserFactory::new(['roles' => ['ROLE_ADMIN']])`.
- Login via helper (once consolidated) or `$client->loginUser($adminUser);`.
- For anonymous denial tests: DO NOT log in; assert 302 (redirect to login) or 403 (forbidden) according to the route’s policy.

### 22.5 Locale / Bilingual Coverage
- Prefer a data provider yielding `['fi']` and `['en']` variants.
- Finnish has no path prefix; English uses `/en/`.
- Admin routes: both `/admin/...` and `/en/admin/...` must behave consistently (authorization + redirects).

### 22.6 Assertions Strategy
Good:
- `assertResponseIsSuccessful()`
- `assertResponseRedirects('/login')`
- `assertSelectorExists('[data-test="event-title"]')`
- `assertSelectorTextContains('h1', 'Dashboard')`
Avoid:
- Raw `assertStringContainsString('<title>Foo</title>', $crawler->html())`
- Chasing multiple redirects blindly (assert intermediate steps if meaningful)

### 22.7 Negative Paths
At least one:
- Invalid credentials / CSRF / unauthorized role
- Invalid form input (missing required, invalid format, boundary violation)
- Temporal boundary not-yet-open / closed scenarios

### 22.8 Mutation Awareness
When adding tests for logic branches:
- Cover both sides of each conditional if domain-relevant.
- Add boundary cases (e.g., equality branches).
- If adding a test purely to kill a survivor mutant, reference it in the test’s docblock: `@see mutation-baseline.md (ClassX:LineY ConditionalBoundary)`.

### 22.9 Naming
Format: `test<BehaviorOrExpectation>` or Given_When_Then style if it clarifies edge conditions.
Examples:
- `testAdminCannotAccessEnglishPrefixedRouteWhenLoggedOut`
- `testEventPublishesAtBoundaryInstant`
- `testSignupWindowRejectsSubmissionAfterClosing`

### 22.10 Final Review Checklist
- [ ] Smallest viable layer chosen
- [ ] Factory states used (no broad fixtures)
- [ ] Time controlled (if needed)
- [ ] Negative path included
- [ ] Structural selectors (no brittle substrings)
- [ ] Bilingual variant (if applicable)
- [ ] Assertions specific & meaningful
- [ ] No unnecessary `->object()` unless raw entity API required
- [ ] Added/updated state documented (if new)

---

## 23. Test Smells & Anti-Patterns

| Smell | Description | Consequence | Preferred Alternative |
|-------|-------------|-------------|-----------------------|
| Brittle Substring Assertion | Searching large HTML chunk for text | Breaks on markup changes | Selector or data-test attribute |
| Over-Scoped Scenario | Huge multi-purpose test method | Hard to pinpoint failures | Split into focused tests |
| Redundant Factory Overbuild | Creating many unused related entities | Slower tests; cognitive noise | Only build what you assert |
| Hidden Time Dependency | Using real now() without clock | Flaky boundary tests | Inject or time-travel with clock |
| Duplicate Locale Methods | Separate FI/EN test methods | Duplication & drift risk | Data provider for locale |
| Broad try/catch Swallow | Catching exceptions without asserting | Masks real failures | Let test fail or assert exception |
| Manual EntityManager Flush Pattern | Forcing flushes inside unit tests | Coupled to persistence | Unit test pure services/entities |
| Global State Mutation (env vars) | Setting env without reset | Cross-test leakage | Service overrides via when@test |
| Magic Number Assertions | Asserting `200 === $status` w/out context | Unclear intent | Use expressive assertion helpers |
| Fixture Reliance | Depending on preloaded global fixtures | Order dependency, fragility | Per-test factory creation |

When encountering a smell:
1. Assess if it blocks mutation/static analysis improvements.
2. Refactor immediately or create a specific TODO with expiry.
3. Document any temporary compromise in a Decision Log entry if non-trivial.

---

## 15a. Factory State Catalog (Canonical States)

Central reference for approved semantic states (extend as new domain invariants emerge).

### EventFactory States
| State | Purpose / Invariants | Notes |
|-------|----------------------|-------|
| `draft()` | Event not published (publishDate null or in future) | Used for draft UI & negative visibility |
| `published()` | Event visible (publishDate <= now) | Must set publishDate if null by policy |
| `scheduled()` | publishDate in future (not yet live) | Often tested with boundary transitions |
| `past()` | Event start/end date before now | Combine with published for archive views |
| `external()` | External URL present, internal page bypass | URL behavior tests |
| `ticketedBasic()` | Tickets enabled minimal config | Extend when ticket domain grows |
| `signupWindowOpen()` | Signup currently open (window inclusive) | Start <= now <= end |
| `signupWindowNotYet()` | Signup not opened yet | now < start |
| `signupWindowEnded()` | Signup closed | now > end |
| `pastUnpublished()` | Composite: past + draft | Edge filtering cases |

### UserFactory / MemberFactory Examples
| State | Purpose |
|-------|---------|
| `admin()` | Roles include ROLE_ADMIN |
| `superAdmin()` | Roles include ROLE_SUPER_ADMIN |
| `unverified()` | Email not verified flag |

Guidelines:
- Each new state MUST:
  - Encapsulate a meaningful domain semantic (not arbitrary data).
  - Avoid overlapping ambiguity (e.g., prefer `scheduled()` over generic `future()`).
  - Initialize all required invariants (e.g. `published()` sets publishDate deterministically).
- Do NOT expose states that simply set a single property unless that property expresses domain semantics.
- Composite states (e.g., `pastUnpublished()`) acceptable for frequent multi-flag combinations.

State Addition Procedure:
1. Add state to factory.
2. Add corresponding row here.
3. Add at least one dedicated unit or functional test using it (prevents unused drift).
4. If state replaces legacy fixture reliance, link removal task for fixture in `todo.md`.

State Review:
- Quarterly (or when domain refactors): remove obsolete states & migrate tests.
- If two states differ only by incidental data, consolidate them.

---

## 23a. Quick Smell Remediation Recipes

| Smell | Fix Strategy (Steps) |
|-------|----------------------|
| Substring Assertion | (1) Add data-test attr in template (2) Replace assertion with selector (3) Remove substring |
| Overbuilt Factory Graph | (1) Remove unused relations (2) Inline attribute overrides (3) Re-run & ensure test still passes |
| Hidden Time Dependency | (1) Inject ClockInterface (2) Use TimeTravelTrait in functional test (3) Add boundary assertions |
| Duplicate Locale Tests | (1) Introduce data provider (2) Parameterize locale in URL (3) Assert differences conditionally if needed |
| Fixture Coupling | (1) Replicate required data via factories (2) Delete fixture dependency (3) Update docs |

---

## 22a. Example End-to-End Functional Test (Putting It All Together)

```php
final class BilingualEventVisibilityTest extends WebTestCase
{
    use TimeTravelTrait;

    public function testPublishedEventVisibleInBothLocales(): void
    {
        $event = EventFactory::new()->published()->create();

        $client = static::createClient();
        $client->request('GET', sprintf('/%d/%s', $event->getYear(), $event->getSlug()));
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-test="event-title"]');

        $client->request('GET', sprintf('/en/%d/%s', $event->getYear(), $event->getSlug()));
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-test="event-title"]');
    }

    public function testScheduledEventNotYetVisible(): void
    {
        $publishAt = new \DateTimeImmutable('+1 hour');
        $event = EventFactory::new()->scheduled()->create(['publishDate' => $publishAt]);

        $client = static::createClient();
        $client->request('GET', sprintf('/%d/%s', $event->getYear(), $event->getSlug()));
        self::assertResponseStatusCodeSame(404);

        $this->freeze($publishAt);
        $client->request('GET', sprintf('/%d/%s', $event->getYear(), $event->getSlug()));
        self::assertResponseIsSuccessful();
    }
}
```

Rationale:
- Uses factory states.
- Covers negative + boundary.
- Bilingual path variant.
- Controlled time for publication boundary.

---

(End of appended documentation sections)
Future revisions must prepend a short CHANGELOG section below.

CHANGELOG
- 2025-10-02: Initial draft authored (satisfies todo task #3 baseline).
