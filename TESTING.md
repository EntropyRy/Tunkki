# TESTING.md

**Comprehensive Testing Guide for Tunkki (Symfony 7, PHP 8.4+)**

> **Quick Start**: See [CLAUDE.md](CLAUDE.md) for AI assistant conventions and canonical commands.
> **Active Tasks**: See [todo.md](todo.md) for current roadmap and open work items.

This document provides detailed guidance for writing, maintaining, and understanding tests in this Symfony application.

## Quick Navigation

- [Test Categories](#1-test-categories--layer-definition)
- [Factory Usage](#2-factories--data-creation)
- [Custom Browser](#3-http-client--site-aware-browser)
- [Time & Clock Abstraction](#4-time-determinism--clock-abstraction)
- [How to Write a New Test](#5-how-to-write-a-new-test-step-by-step)
- [Test Smells & Anti-Patterns](#6-test-smells--anti-patterns)
- [Factory State Catalog](#7-factory-state-catalog)

---

## 1. Test Categories & Layer Definition

We maintain three primary test layers. Choose the lowest layer that gives meaningful confidence.

### 1.1 Unit Tests
- **Namespace**: `App\Tests\Unit\...`
- No kernel boot. Pure PHP objects only (value objects, domain services, helpers)
- No database, filesystem, HTTP, container, or external bundles
- Fast feedback; should run in < 100ms per file in aggregate
- Use PHPUnit mocks sparingly; prefer real collaborators when simple

### 1.2 Integration Tests
- **Namespace**: `App\Tests\Integration\...`
- Boot the Symfony kernel, access the container, call real services
- Avoid HTTP layer (do not use `KernelBrowser` unless specifically validating a request/response transformer)
- Use real database connection but keep data local to each test (prefer factories over fixtures)
- Examples: Repository queries, service wiring, data mappers, security voters, mailer logic (sans external transport calls)

### 1.3 Functional (End-to-End Slice) Tests
- **Namespace**: `App\Tests\Functional\...`
- Exercise HTTP entrypoints with `SiteAwareKernelBrowser`
- Assert routing, controllers, templates, translations, forms, security (firewalls/roles), page structure
- Keep scenarios focused; avoid multi-purpose "mega tests"
- Use factories for test-specific data. Do not rely on global cross-test mutable state

### 1.4 Panther Tests (Browser/JavaScript)
- **Namespace**: `App\Tests\Functional\...*PantherTest.php`
- Real browser (Chrome/Chromedriver) for LiveComponent interactions, WebRTC, etc.
- Reserved for behaviors that truly need JavaScript (avoid for basic HTTP flows)
- Uses separate SQLite DB (`var/test_panther.db`), dedicated `panther` kernel

---

## 2. Factories & Data Creation

**Primary mechanism**: Zenstruck Foundry (`src/Factory/*Factory.php`)

### 2.1 Core Principles
- Create only the minimal entities required for a test
- Use semantic state methods (e.g. `EventFactory::new()->published()`)
- Avoid depending on large canonical fixture datasets
- Keep state method names business-oriented (`published()`, `signupEnabled()`, `finished()`, `active()`)

### 2.2 Factory Usage Patterns

**Basic creation**:
```php
$event = EventFactory::new()->published()->create();
$user = UserFactory::new()->create();
```

**Inline overrides** (use sparingly for IDs/slugs/names you must assert):
```php
$event = EventFactory::new()
    ->published()
    ->create(['url' => 'test-slug', 'name' => 'Test Event']);
```

**Associations**:
```php
$product = ProductFactory::new()->ticket()->forEvent($event)->create();
$item = CartItemFactory::new()->forProduct($product)->withQuantity(3)->create();
```

### 2.3 When to Add a New State
- A pattern appears in ≥2 test classes OR a single test reads unclearly without a semantic label
- Prefer naming from domain perspective (`pastUnpublished()`) over technical attribute lists
- Document new states in the [Factory State Catalog](#7-factory-state-catalog)

### 2.4 Avoid
- Chaining large numbers of low-signal inline overrides
- Creating broad "do-everything" states (anti-pattern: `fullyLoaded()`)
- Direct `new Entity()` + manual persisting (add factory/state instead)

---

## 3. HTTP Client & Site-Aware Browser

### 3.1 Mandate: SiteAwareKernelBrowser is REQUIRED

**Context**: Sonata PageBundle with "host path by locale" multisite strategy requires wrapping each request in `SiteRequest`.

**Pattern** (mandatory for all functional tests):
```php
protected function setUp(): void
{
    parent::setUp();
    $this->initSiteAwareClient();
    $this->seedClientHome('fi'); // or 'en'
}
```

### 3.2 Why It's Required
- Guarantees `Sonata\PageBundle\Request\SiteRequest` wrapping (multi-site & locale context)
- Normalizes host, base URL, path info
- Ensures parity between test routing and production site-aware routing
- Prevents `RuntimeException: You must configure runtime on your composer.json` errors

### 3.3 Prohibited Patterns
- Direct usage of `static::createClient()` in functional tests
- Manual instantiation of `KernelBrowser` or `SiteAwareKernelBrowser`
- Mixing raw `KernelBrowser` and `SiteAwareKernelBrowser` in the same test class

### 3.4 BrowserKit Request Guidance

**Always issue a request before assertions**:
```php
// Good: Seed homepage before assertions
$this->seedClientHome('en');
$this->client->request('GET', '/some/path');
self::assertResponseIsSuccessful();

// Bad: Accessing response without request
// $this->client->getResponse(); // ❌ Error!
```

**After programmatic login, stabilize session**:
```php
$this->loginUser($user);
$this->stabilizeSessionAfterLogin(); // Persists security token
$this->client->request('GET', '/profile');
```

### 3.5 Helper Methods (from FixturesWebTestCase)
- `initSiteAwareClient()` - Initialize site-aware browser
- `seedClientHome($locale)` - Seed homepage request
- `seedLoginPage($locale)` - Seed login page
- `ensureClientReady()` - Ensure at least one request has been made
- `newClient()` - Create fresh client instance

### 3.6 Assertions on SiteAwareKernelBrowser
Use client-based assertions for compatibility:
```php
// Good: Use client assertions
$this->client->assertSelectorExists('form[name="cart"]');
$this->client->assertSelectorTextContains('.event-name', 'Test Event');

// Bad: Direct WebTestCase assertions don't work with custom browser
// $this->assertSelectorExists('form'); // ❌
```

---

## 4. Time Determinism & Clock Abstraction

### 4.1 Policy Summary
All time-dependent domain/service logic obtains "now" from `ClockInterface` (never direct `new DateTime()`).

### 4.2 Clock Service Configuration

**Test environment** (`services.yaml when@test`):
```yaml
when@test:
  parameters:
    test.fixed_datetime: "2025-01-01T12:00:00+00:00"
  services:
    App\Time\ClockInterface:
      class: App\Time\MutableClock
      arguments: ["%test.fixed_datetime%"]
      public: true
```

### 4.3 Allowed Direct DateTime Usage
- Entity lifecycle callbacks (`prePersist`/`preUpdate`)
- Doctrine migrations
- Tests (arranging controlled timestamps)
- Factory default seed values

### 4.4 Required Clock Injection
Everything else MUST inject `ClockInterface`:
- Controllers
- Services
- Repositories
- Security components
- Domain service classes

### 4.5 TimeTravelTrait (Test Time Manipulation)

**Location**: `tests/Support/TimeTravelTrait.php`

**Methods**:
```php
use App\Tests\Support\TimeTravelTrait;

// Get current test time
$now = $this->now();

// Freeze to specific instant
$this->freeze('2025-01-01T12:00:00+00:00');
$this->travelTo(new \DateTimeImmutable('2025-06-15'));

// Relative travel
$this->travel('+15 minutes');
$this->travel('-2 days');

// Convenience methods
$this->travelSeconds(30);
$this->travelMinutes(15);
$this->travelHours(2);
$this->travelDays(7);
```

**Example usage**:
```php
public function testEventTransitionsAtPublishBoundary(): void
{
    $publishAt = new \DateTimeImmutable('2025-01-01T12:00:00+00:00');
    $event = EventFactory::new()->draft()->create(['publishDate' => $publishAt]);

    $this->freeze('2025-01-01T11:59:59+00:00');
    self::assertFalse($decider->isPublished($event));

    $this->travelSeconds(1); // Now at boundary
    self::assertTrue($decider->isPublished($event));
}
```

### 4.6 Dual Clock Pattern (Legacy Entities)

Some entity methods use `new \DateTimeImmutable()` directly. Use dual clock pattern:
```php
private function getDates(): array
{
    $realNow = new \DateTimeImmutable(); // For Entity methods
    $testNow = $this->now(); // For services using ClockInterface
    return [$realNow, $testNow];
}

// Usage
[$realNow, $testNow] = $this->getDates();
$event = EventFactory::new()->create([
    'publishDate' => $testNow->modify('-5 minutes'), // ClockInterface
    'ticketPresaleStart' => $realNow->modify('-1 day'), // Entity method
]);
```

---

## 5. How to Write a New Test (Step-by-Step)

### 5.1 Decide the Test Layer
Use the cheapest viable layer:
- **Unit**: Pure logic, no container, no DB
- **Functional**: HTTP kernel (routing, controllers, security, forms)
- **Integration**: Cross-service wiring, Doctrine queries

### 5.2 Prepare Data (Factories Only)
```php
// Start with semantic state
$event = EventFactory::new()->published()->ticketed()->create();

// Minimal inline overrides
$event = EventFactory::new()->published()->create([
    'url' => 'test-slug-'.uniqid(),
]);
```

### 5.3 Handle Authentication
```php
// Create user with roles
$admin = UserFactory::new(['roles' => ['ROLE_ADMIN']])->create();

// Login
$this->loginUser($admin);
$this->stabilizeSessionAfterLogin();
```

### 5.4 Write Assertions
**Prefer**:
- `assertResponseIsSuccessful()`
- `assertResponseStatusCodeSame(403)`
- `assertResponseRedirects('/login')`
- `$this->client->assertSelectorExists('[data-test="event-title"]')`
- `$this->client->assertSelectorTextContains('h1', 'Dashboard')`

**Avoid**:
- `assertStringContainsString('<title>Foo</title>', $html)`
- Chasing multiple redirects blindly

### 5.5 Include Negative Paths
At least one:
- Invalid credentials / CSRF / unauthorized role
- Invalid form input (missing required, invalid format)
- Temporal boundary not-yet-open / closed scenarios

### 5.6 Locale / Bilingual Coverage
Use data providers:
```php
use PHPUnit\Framework\Attributes\DataProvider;

#[DataProvider('localeProvider')]
public function testShopAccessible(string $locale): void
{
    $this->seedClientHome($locale);
    $path = $locale === 'en' ? '/en/shop' : '/shop';
    $this->client->request('GET', $path);
    $this->assertResponseIsSuccessful();
}

public static function localeProvider(): array
{
    return [['fi'], ['en']];
}
```

### 5.7 Test Naming
Format: `test<BehaviorOrExpectation>`

Examples:
- `testAdminCannotAccessWhenLoggedOut`
- `testEventPublishesAtBoundaryInstant`
- `testSignupWindowRejectsAfterClosing`

### 5.8 Final Checklist
- [ ] Smallest viable layer chosen
- [ ] Factory states used (no broad fixtures)
- [ ] Time controlled (if needed)
- [ ] Negative path included
- [ ] Structural selectors (no brittle substrings)
- [ ] Bilingual variant (if applicable)
- [ ] Assertions specific & meaningful

---

## 6. Test Smells & Anti-Patterns

| Smell | Description | Consequence | Preferred Alternative |
|-------|-------------|-------------|-----------------------|
| Brittle Substring Assertion | Searching HTML for text | Breaks on markup changes | Selector or `data-test` attribute |
| Over-Scoped Scenario | Huge multi-purpose test | Hard to pinpoint failures | Split into focused tests |
| Redundant Factory Overbuild | Creating unused entities | Slower tests, noise | Only build what you assert |
| Hidden Time Dependency | Using real `now()` | Flaky boundary tests | Inject or time-travel with clock |
| Duplicate Locale Methods | Separate FI/EN tests | Duplication & drift | Data provider for locale |
| Broad try/catch Swallow | Catching without asserting | Masks real failures | Let test fail or assert exception |
| Global State Mutation | Setting env without reset | Cross-test leakage | Service overrides via `when@test` |
| Fixture Reliance | Depending on preloaded data | Order dependency | Per-test factory creation |

### Quick Remediation Strategies
| Smell | Fix Strategy |
|-------|--------------|
| Substring Assertion | Add `data-test` attr in template → Replace with selector → Remove substring |
| Overbuilt Factory | Remove unused relations → Inline attribute overrides → Re-run test |
| Hidden Time Dependency | Inject ClockInterface → Use TimeTravelTrait → Add boundary assertions |
| Duplicate Locale Tests | Introduce data provider → Parameterize locale in URL → Assert conditionally |
| Fixture Coupling | Replicate data via factories → Delete fixture dependency → Update docs |

---

## 7. Factory State Catalog

Central reference for approved semantic states (extend as new domain invariants emerge).

### EventFactory States
| State | Purpose / Invariants |
|-------|----------------------|
| `draft()` | Event not published (publishDate null or in future) |
| `published()` | Event visible (publishDate <= now) |
| `scheduled()` | publishDate in future (not yet live) |
| `past()` | Event start/end date before now |
| `external()` | External URL present, internal page bypass |
| `ticketed()` / `ticketedBasic()` | Tickets enabled minimal config |
| `signupWindowOpen()` | Signup currently open (start <= now <= end) |
| `signupWindowNotYet()` | Signup not opened yet (now < start) |
| `signupWindowEnded()` | Signup closed (now > end) |
| `pastUnpublished()` | Composite: past + draft |

### ProductFactory States
| State | Purpose |
|-------|---------|
| `ticket()` | Ticket product (isTicket=true) |
| `forEvent(Event $event)` | Link product to event |

### CartFactory / CartItemFactory States
| State | Purpose |
|-------|---------|
| `withItems(array $items)` | Create cart with items |
| `forProduct(Product $product)` | Link item to product |
| `withQuantity(int $qty)` | Set item quantity |

### CheckoutFactory States
| State | Purpose (status values) |
|-------|-------------------------|
| `open()` | Payment in progress (status=0) |
| `completed()` | Payment successful (status=1) |
| `expired()` | Session timeout (status=-1) |
| `processed()` | Tickets sent (status=2) |

### UserFactory / MemberFactory States
| State | Purpose |
|-------|---------|
| `admin()` | Roles include ROLE_ADMIN |
| `superAdmin()` | Roles include ROLE_SUPER_ADMIN |
| `active()` | Active member (for Member) |
| `withoutUser()` | Member without User (rare, negative tests only) |

### State Addition Procedure
1. Add state method to factory
2. Add corresponding row to this catalog
3. Add at least one test using it (prevents unused drift)
4. Document in commit message

---

## 8. Test Environment & Configuration

### 8.1 APP_ENV=test Guard
All PHPUnit executions MUST run with `APP_ENV=test`. The Makefile enforces this automatically.

**Why**:
- Symfony only registers `test.service_container` in test environment
- DAMA/Foundry depend on test kernel boot
- Running with `APP_ENV=dev` causes `LogicException` errors

**Canonical commands**:
```bash
make test                    # Full suite
make test-functional         # Functional only
make test-unit              # Unit only

# Single file (explicit env):
docker compose exec -T -e APP_ENV=test fpm php vendor/bin/phpunit tests/Functional/EventScenariosTest.php
```

### 8.2 Service Overrides (when@test)

**Location**: `config/services.yaml` (consolidated `when@test:` block)

**Current overrides**:
- **Clock**: `MutableClock` with fixed datetime
- **Mailer**: `null://null` transport
- **Messenger**: `sync://` transport (no async workers needed)
- **Notifier**: Empty chatter/texter transports (no external notifications)

**Policy**:
- Do NOT create new `config/packages/test/` subdirectory
- Add new overrides inside existing `when@test:` block
- Document in Decision Log (todo.md)

### 8.3 Database Isolation (DAMA)
- Each test runs inside a transaction rolled back afterward
- Fast and clean (no cross-test pollution)
- DO NOT depend on previous test's database mutations
- DO NOT manually manage transactions in tests

### 8.4 CMS Baseline (Sonata Page)
- `CmsBaselineStory` loaded once per test run in `tests/bootstrap.php`
- Seeds exactly two Sites (fi default, en with `/en/`) and required pages
- Snapshots created and force-enabled for routing
- Tests MUST NOT modify Sites/Pages (or restore state if they do)

### 8.5 Parallel Execution (ParaTest)
**Status**: PARALLEL-SAFE (as of 2025-10-16 with advisory locking)

**Default**: Parallel execution enabled (`USE_PARALLEL=1`)
```bash
make test                    # Auto-detects CPU count, runs parallel

# Opt-out (serial execution for debugging):
USE_PARALLEL=0 make test
```

**Safety**: Database advisory locks prevent CMS baseline race conditions across parallel processes.

---

## 9. Authentication & Login Helpers

### 9.1 LoginHelperTrait

**Location**: `tests/Support/LoginHelperTrait.php`

**Methods**:
```php
// Login as specific member
$member = MemberFactory::new()->active()->create();
$this->loginAsMember($member);

// Login by email (finds or creates user)
$this->loginAsEmail('test@example.com');

// After login, seed new client for subsequent requests
$this->seedClientHome('fi');
```

### 9.2 Authentication State Assertions

**From FixturesWebTestCase**:
```php
$this->assertNotAuthenticated('User should not be authenticated');
$this->assertAuthenticated('User should be authenticated');
```

### 9.3 Security Behavior Patterns

**Anonymous user denied**:
```php
$this->client->request('GET', '/admin/dashboard');
$this->assertResponseStatusCodeSame(302); // Redirect to /login
$this->assertResponseRedirects('/login');
```

**Authenticated user denied**:
```php
$this->loginAsMember($regularMember);
$this->client->request('GET', '/admin/dashboard');
$this->assertResponseStatusCodeSame(403); // Forbidden
```

---

## 10. Locale & Internationalization

### 10.1 Locale Strategy
- **Finnish (default)**: no prefix (e.g., `/2025/event-slug`)
- **English**: `/en/` prefix (e.g., `/en/2025/event-slug`)
- **Admin routes**: both `/admin/...` and `/en/admin/...` supported
- **OAuth endpoints**: canonical unprefixed (`/oauth`, `/oauth/authorize`)

### 10.2 Bilingual Testing Pattern

Use data providers:
```php
use PHPUnit\Framework\Attributes\DataProvider;

#[DataProvider('localeProvider')]
public function testEventAccessible(string $locale): void
{
    $this->seedClientHome($locale);
    $path = $locale === 'en'
        ? '/en/2025/test-event'
        : '/2025/test-event';

    $this->client->request('GET', $path);
    $this->assertResponseIsSuccessful();
}

public static function localeProvider(): array
{
    return [['fi'], ['en']];
}
```

### 10.3 Event List Gating Rules

**Announcements page**:
- Lists only public announcement events
- Repository: `findPublicEventsByType('announcement')` (requires `published=true` AND `publishDate <= now`)

**Events page**:
- Active members: All events except announcements (`findAllByNotType('announcement')`) - no publish gating
- Others (anonymous/inactive): Only public events (`findPublicEventsByNotType('announcement')`)

**Test guidance**:
- Use `EventFactory::new()->published()` for public visibility tests
- Use `EventFactory::new()->draft()` for negative cases
- Active member tests require `Member::getIsActiveMember() === true`

---

## 11. Assertions & DOM Inspection

### 11.1 Preferred Assertions

**Response status**:
```php
$this->assertResponseIsSuccessful();
$this->assertResponseStatusCodeSame(403);
$this->assertResponseRedirects('/login');
```

**Structural selectors** (via SiteAwareKernelBrowser):
```php
$this->client->assertSelectorExists('form[name="cart"]');
$this->client->assertSelectorTextContains('h1', 'Dashboard');
$this->client->assertSelectorExists('[data-test="event-title"]');
```

### 11.2 Anti-Patterns to Avoid

❌ Manual content substring assertions:
```php
// Bad
$this->assertStringContainsString('<h1>Welcome</h1>', $response->getContent());

// Good
$this->client->assertSelectorTextContains('h1', 'Welcome');
```

❌ Redirect chasing loops without meaningful checks
❌ Accessing `$client->getResponse()` before any request
❌ Full HTML equality checks (brittle)

---

## 12. Form Validation & Negative Coverage

### 12.1 Conventions
- Treat each validation dimension as its own test method
- Use consistent naming: `test<Context>Rejects<Condition>()`
- Assert specific error selectors instead of raw text

### 12.2 Example Pattern
```php
public function testCartRejectsInvalidEmail(): void
{
    $event = EventFactory::new()->published()->ticketed()->create();
    $this->seedClientHome('fi');

    $crawler = $this->client->request('GET', "/2025/{$event->getUrl()}/kauppa");
    $form = $crawler->filter('form[name="cart"]')->form([
        'cart[email]' => 'not-an-email',
    ]);

    $this->client->submit($form);
    $this->assertResponseStatusCodeSame(422); // Unprocessable Entity
}
```

---

## 13. Mutation & Coverage

### 13.1 Coverage Scope
**Excluded from instrumentation** (in `phpunit.dist.xml`):
- `src/Admin` (Sonata configuration glue, low business value)

**Focus coverage on**:
- Domain logic (entities, value objects, services)
- Security components
- Temporal logic
- Repositories

### 13.2 Mutation Testing (Infection)

**Commands**:
```bash
make infection FILTER=src/Security  # Focused run
make infection                      # Full baseline
```

**Baseline metrics** (2025-10-08):
- Security: 0% MSI → improved with unit tests
- Repository: 100% covered MSI
- Entity: 95% MSI (10 survivors, boundary conditions)

**Thresholds**: Currently set to 0 in `infection.json.dist` for exploratory runs

### 13.3 Driving Tests from Survivors
- Escaped conditional mutants → Add focused unit test on branch logic
- Recurrent repository escapes → Add repository tests with precise fixtures
- Use mutation survivors to inform missing unit tests

---

## 14. Performance & Parallelization

### 14.1 Keep Tests Lean
- Only create entities needed for the assertion
- Use factories for minimal test data
- Consider `@group slow` for extremely slow tests

### 14.2 Parallel Execution
**Default**: Enabled via ParaTest
```bash
make test                    # Parallel (auto-detect CPUs)
USE_PARALLEL=0 make test    # Serial (debugging)
```

**Safety**:
- Per-process database isolation via DAMA transactions
- CMS baseline uses advisory locks (no race conditions)

### 14.3 Test Profiling
```bash
make test-profile           # Profile durations per file → metrics/test-times.txt
```

---

## 15. Panther Tests (Browser/JavaScript)

### 15.1 When to Use Panther
Reserve for behaviors that truly need a browser:
- LiveComponent interactions
- Frontend JS flows
- WebRTC
- Real browser testing

Prefer BrowserKit functional tests for everything else.

### 15.2 Prerequisites (One-Time Setup)
```bash
# Seed CMS baseline for test DB
docker compose exec -T -e APP_ENV=test fpm php bin/console entropy:cms:seed

# Download Chrome/Chromedriver binaries
docker compose exec -T -e APP_ENV=test fpm php vendor/bin/bdi detect drivers
```

### 15.3 Running Panther Tests
```bash
docker compose exec -T -e APP_ENV=test fpm php vendor/bin/phpunit tests/Functional/StreamWorkflowPantherTest.php
```

### 15.4 Environment
- Uses dedicated `panther` kernel (APP_ENV=panther)
- Separate SQLite DB (`var/test_panther.db`)
- Internal web server on `http://localhost:9080`
- Site hostnames stay `localhost` (Docker + CI compatible)

### 15.5 Debugging
- HTML snapshots: `var/test_login_page.html`, `var/test_stream_page.html`
- Non-headless mode: `PANTHER_NO_HEADLESS=1` (local debugging only)

---

## 16. Pre-Commit Hook (Local Quality Gate)

### 16.1 Enabling (One-Time)
```bash
git config core.hooksPath .githooks
chmod +x .githooks/pre-commit
```

### 16.2 What It Runs
1. PHPStan fast subset (`make stan-fast`)
2. Unit test suite (`make test-unit`)
3. php-cs-fixer dry-run (if available)

### 16.3 Skip Mechanisms
```bash
SKIP_QA=1 git commit -m "msg"              # Skip via env var
git commit -m "msg [skip qa]"              # Skip via commit message
FORCE_QA=1 git commit -m "msg"             # Force run
```

### 16.4 Failure Behavior
Any failing step aborts the commit with a summary. Override intentionally via `SKIP_QA=1` (should be rare).

---

## 17. Quick Reference Commands

### Run Tests
```bash
make test                          # Full suite (parallel)
make test-unit                     # Unit tests only
make test-functional               # Functional tests only
USE_PARALLEL=0 make test          # Serial execution (debugging)
make test-profile                  # Profile test durations
```

### Static Analysis
```bash
make stan                          # Full PHPStan (level=5)
make stan-fast                     # Analyze src/ only
make stan-json                     # JSON output
```

### Mutation Testing
```bash
make infection FILTER=src/Security # Focused mutation
make infection                     # Full baseline
```

### Coverage
```bash
make coverage                      # Generate coverage report
```

### Cleanup
```bash
make clean                         # Clean caches (coverage, infection, PHPStan)
```

### CMS & Debug Scripts
```bash
make scripts-debug-baseline        # Verify FI/EN sites and pages
make scripts-route-debug           # Debug Sonata routing
```

---

## 18. Example End-to-End Test

```php
<?php

namespace App\Tests\Functional;

use App\Factory\EventFactory;
use App\Tests\Support\FixturesWebTestCase;
use App\Tests\Support\TimeTravelTrait;

final class EventVisibilityTest extends FixturesWebTestCase
{
    use TimeTravelTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('fi');
    }

    public function testPublishedEventVisibleInBothLocales(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'test-event-'.uniqid(),
        ]);

        // Test Finnish
        $this->client->request('GET', sprintf('/%d/%s', $event->getYear(), $event->getUrl()));
        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('[data-test="event-title"]');

        // Test English
        $this->client->request('GET', sprintf('/en/%d/%s', $event->getYear(), $event->getUrl()));
        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('[data-test="event-title"]');
    }

    public function testScheduledEventNotYetVisible(): void
    {
        $publishAt = new \DateTimeImmutable('+1 hour');
        $event = EventFactory::new()->scheduled()->create([
            'publishDate' => $publishAt,
            'url' => 'future-event-'.uniqid(),
        ]);

        // Before publish time
        $this->client->request('GET', sprintf('/%d/%s', $event->getYear(), $event->getUrl()));
        $this->assertResponseStatusCodeSame(404);

        // At publish boundary (time travel)
        $this->freeze($publishAt);
        $this->client->request('GET', sprintf('/%d/%s', $event->getYear(), $event->getUrl()));
        $this->assertResponseIsSuccessful();
    }
}
```

---

## Maintenance

**Update rules**:
- Append new conventions with concise rationale
- Do not remove historical context without summarizing first
- When lifting a transitional constraint, add deprecation note
- Keep examples current and working

**Last updated**: 2025-11-06 (Major consolidation refactor)
