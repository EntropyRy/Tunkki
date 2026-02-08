# CLAUDE.md

Guidance & Command Reference for AI Assistant + Contributors
Project: Tunkki (Symfony 7, PHP 8.4+)

This document defines how an automated assistant (and humans) should interact with the repository safely and consistently. Treat it as an execution contract. For detailed testing patterns, examples, and factory catalogs, see [TESTING.md](TESTING.md).

---

## 1. Core Principles

1. Never guess paths -- discover (or list) before reading/modifying.
2. Prefer existing tooling (Makefile targets, factories, test helpers) over ad-hoc scripts.
3. Avoid introducing broad suppressions (static analysis, mutation, linters). Always document exceptions.
4. Maintain isolation: no cross-test state leakage, no global fixture dependencies.
5. Use structural or semantic assertions (selectors, domain invariants) rather than brittle substrings. If a selector doesn't exist in the template, add a semantic class/id and assert via selectors.
6. Locale strategy: Finnish has no prefix; English uses `/en`. Admin area AND public routes follow this rule (except OAuth endpoints, which remain canonical `/oauth*` -- see section 5.4).
7. All quality tooling (PHPStan, Infection) runs inside the Docker FPM container (PHP 8.4).
8. No unit tests for entities; functional tests must cover entity behavior.

---

## 2. Environment & Execution

**Container**: service `fpm`, working directory `/var/www/symfony` (repo root bind-mounted).

All PHP commands MUST run inside the Docker FPM container. Use Makefile targets (preferred) or explicit container invocation:

```
docker compose exec -T fpm php ./bin/console cache:clear
docker compose exec -T fpm php ./bin/console doctrine:migrations:migrate --no-interaction
```

Do NOT run `./bin/console` or `vendor/bin` tools directly on the host unless the host PHP >= 8.4 with matching extensions. If you see platform/version mismatch errors, re-run inside the container.

---

## 3. Commands Reference

### 3.1 CRITICAL: Always Use Makefile for Tests

**NEVER** run tests via direct `docker compose exec ... phpunit` commands. Always use Makefile targets -- they handle database setup, ParaTest parallel execution, and environment variables.

### 3.2 Test Targets

| Command | Purpose |
|---------|---------|
| `make test` | Full suite (parallel, with coverage) |
| `make test-unit` | Unit tests only |
| `make test-functional` | Functional tests only |
| `make test-panther` | Panther browser tests (serial) |
| `make test-ci` | CI-style (fail-fast, no coverage) |
| `make test-one FILE=tests/Unit/SomeTest.php` | Single test file |
| `make test-one-filter FILE=tests/Path/Test.php METHOD=testName` | Single test method |
| `make coverage` | Full suite with coverage report |

To run tests serially (debugging): `USE_PARALLEL=0 make test`

### 3.3 Static Analysis

| Command | Purpose |
|---------|---------|
| `make stan` | Full PHPStan (level=5 default) |
| `make stan-fast` | Analyze `src/` only |
| `make stan-delta` | Changed files vs `origin/main` |
| `make stan PHPSTAN_LEVEL=max` | Run at max level |

### 3.4 Mutation Testing

| Command | Purpose |
|---------|---------|
| `make infection FILTER=src/Security` | Focused mutation run |
| `make infection` | Full baseline run |

### 3.5 Utilities

| Command | Purpose |
|---------|---------|
| `make clean` | Clear coverage + Infection + PHPStan caches |
| `make clean-test-db` | Reset test database (drop/create/schema/seed) |
| `make lint-datetime` | Enforce clock policy (forbidden `new DateTime` in service layer) |
| `make doctor` | Environment & tool diagnostics |
| `make update-dev` | Full dev environment update (pull, build, deps, lint, rector) |

### 3.6 Override Variables

```
make stan PHP_EXEC="docker compose exec -T fpm php" PHPSTAN_LEVEL=5
make test PARA_PROCS=4 USE_PARALLEL=1
```

---

## 4. Test Conventions

### 4.1 Rules

| Aspect | Rule |
|--------|------|
| Client | Always use `SiteAwareKernelBrowser` via `initSiteAwareClient()` in setUp. See TESTING.md section 3. |
| Isolation | DAMADoctrineTestBundle transactions. No persistent cross-test DB state. |
| Data creation | Zenstruck Foundry factories. No broad global fixtures for new tests. |
| Helper traits | `tests/Support/` (LoginHelperTrait, LocaleDataProviderTrait, MetaAssertionTrait, FormErrorAssertionTrait, TimeTravelTrait, UniqueValueTrait). |
| Locales | Use data providers for bilingual cases; Finnish unprefixed, English under `/en/`. |
| Assertions | Prefer `assertResponseIsSuccessful`, `$this->client->assertSelectorExists()`, structural DOM queries. |
| Negative coverage | Include invalid form submissions, expired windows, unauthorized access. At least one per feature test. |
| Security | Explicit tests for role denial, session invalidation, CSRF, unverified flows. |
| No substrings | Never use `assertStringContainsString` on HTML. Add semantic selectors if needed. |
| No hardcoded dates | Use `TimeTravelTrait` or `getDates()` dual-clock pattern. See TESTING.md section 4. |

### 4.2 Adding a New Test (Quick Checklist)

1. Choose layer: Unit (pure logic) or Functional (HTTP/DB/routing with `SiteAwareKernelBrowser`).
2. Create entities via Foundry factories (no shared fixture reliance).
3. If locale-sensitive, add data provider `[['fi'], ['en']]`.
4. Assert structurally: `assertResponseIsSuccessful()`, `$this->client->assertSelectorExists()`.
5. Include at least one negative scenario (invalid input / access denial).
6. No environment variable mutation; prefer service overrides.

For comprehensive guide with examples: TESTING.md section 5.
For factory state catalog: TESTING.md section 7.

### 4.3 Site-Aware Client (Sonata Page Multisite)

The project uses Sonata PageBundle with "host path by locale" multisite strategy. Every functional test MUST:

1. Call `$this->initSiteAwareClient()` in setUp (registers with WebTestCase).
2. Use `$this->client->assertSelectorExists()` (not `$this->assertSelectorExists()`).
3. Never mix raw `KernelBrowser` and `SiteAwareKernelBrowser` in the same test class.
4. Never call `static::bootKernel()` + `new SiteAwareKernelBrowser(...)` directly.

For full details and troubleshooting: TESTING.md section 3.

---

## 5. Routing & Locale Policies

### 5.1 Public Locale Strategy

- Finnish (default): no prefix
- English: `/en/` prefix

### 5.2 Admin Bilingual

- Accept `/admin/...` and `/en/admin/...` (security unified via `^/(en/)?admin/`).
- Tests must cover both privileged and unprivileged paths.

### 5.3 Event URLs

Internal events:
- FI: `/{year}/{slug}`
- EN: `/en/{year}/{slug}`

External events:
- `externalUrl=true` => `getUrlByLang()` returns raw external URL (passthrough)
- No expectation of internal localized pages resolving

### 5.4 OAuth Endpoints (Canonical)

- Remain unprefixed: `/oauth`, `/oauth/authorize`, `/oauth/check_*`
- Prefixed variants (`/en/oauth/...`) should 301 redirect or 404.
- Consent page locale determined via user preference or Accept-Language, not path prefix.

---

## 6. Static Analysis (PHPStan)

Config: `symfony/phpstan.neon`. No broad baseline -- fix high-signal issues first.

### 6.1 Priority Remediation Order

1. Generics (`@extends`) for Sonata Admin & CRUD controllers.
2. Nullability mismatches (entity fields vs PHP types).
3. Return/param type completions (avoid implicit mixed).
4. DateTimeImmutable consistency (no inadvertent mutable DateTime).
5. Undefined symbol/property access.
6. Dead private properties / unused injections.

### 6.2 Generics Annotation Pattern

```php
/** @extends AbstractAdmin<App\Entity\Event> */
final class EventAdmin extends AbstractAdmin

/** @extends CRUDController<App\Entity\Event> */
final class EventAdminController extends CRUDController
```

If entity type is unknown, temporarily use `<object>` but raise a task to replace it.

### 6.3 Ignore Policy

- Each ignore must include rationale + expiration review date.
- No wildcard ignores on entire directories.
- No broad `ignoreErrors` blocks.

---

## 7. Mutation Testing (Infection)

Config: `symfony/infection.json.dist`. Initial scope: `src/Repository`, `src/Security`.

Do not raise MSI thresholds until high-value survivors are addressed and structural negative tests are stable. Current thresholds: `--min-msi=0`.

### 7.1 Using Survivors to Drive Tests

- Escaped conditional mutants => add focused unit test on branch logic.
- Recurrent repository logic escapes => add repository tests with precise fixtures/factories.
- Missing PHPStan generics => poor type inference => harder to write tight tests.

---

## 8. Service Overrides (Test Environment)

### 8.1 Current Overrides

- Lower password hashing cost
- Null mailer transport (`null://null`)
- Fixed clock service (`MutableClock`)
- Synchronous messenger (`sync://`)
- Empty chatter/texter transports (no external notifications)

### 8.2 Configuration Policy

- Use `when@test` blocks inside existing YAML files (e.g. `services.yaml`).
- Do NOT create new subdirectories under `config/` for environment-specific overrides.
- Only introduce a new file if a third-party bundle recipe mandates it.

### 8.3 ClockInterface Adoption

1. New services requiring current time MUST depend on `ClockInterface`.
2. Entities should not inject the clock; domain services compute time-dependent state and pass values in.
3. When replacing `new DateTime()` in services, preserve timezone assumptions; prefer `$clock->now()->modify(...)`.
4. Document any new override in TESTING.md (Service Overrides section).

---

## 9. AI Agent Behavior

When asked to:

- **"Add test"**: Propose factory-driven, locale-aware, structural assertions. Follow section 4.2.
- **"Fix failing test"**: Inspect isolation assumptions & factory correctness before altering assertions.
- **"Improve performance"**: Suggest parallelization AFTER confirming no shared temp dirs / cross-test global writes.
- **"Handle static analysis noise"**: Group by category, resolve in priority order (section 6.1), avoid knee-jerk ignores.

Never:

- Introduce broad `ignoreErrors` blocks.
- Replace structural assertions with loose substrings for convenience.
- Hardcode secrets or environment credentials.
- Use `<object>` generic permanently without raising a follow-up task.
- Replace with null/fake transport without documenting the override.

---

End of CLAUDE.md
