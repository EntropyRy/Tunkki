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
| Mutation (baseline)   | `make infection-baseline`             | Append metrics manually |
| Static analysis full  | `make stan`                           | Uses level=5 unless overridden |
| Static analysis fast  | `make stan-fast`                      | Analyzes `src/` only |
| Static analysis JSON  | `make stan-json`                      | Outputs `metrics/phpstan-report.json` |
| Clean caches          | `make clean`                          | Coverage + Infection + PHPStan caches |
| Metrics snapshot stub | `make metrics-snapshot`               | Creates timestamped scaffold |
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
| Substring purge      | Avoid raw HTML substrings except when asserting a micro invariant not exposed structurally (document such cases). |

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

Metrics:
- Record counts in: `metrics/phpstan-initial.md`
- Post-fix snapshot: `metrics/phpstan-triage.md`

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
Record results in `metrics/mutation-baseline.md`:
- Mutants generated / killed / escaped
- MSI / Covered MSI
- High-value survivors (security/domain invariants)

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
Remaining acceptable substrings must be:
- Documented with reason (no structural selector available).
- Short-term until `data-test` attributes added.

---

## 10. Metrics & Historical Logging

Directory: `metrics/`
Files:
- `2025-10-02-baseline.md`
- `2025-10-02-post-isolation.md`
- `mutation-baseline.md`
- `phpstan-initial.md`
- `phpstan-triage.md`

Policy:
- Append-only (never rewrite historical snapshots).
- Each snapshot includes: date, commit, tests, runtime, coverage, notable structural changes, next focus.

---

## 11. Adding a New Test (Recipe)

1. Decide layer:
   - Pure logic? => Unit (no kernel)
   - HTTP / DB / routing? => Functional (use SiteAwareKernelBrowser)
2. Create entities via Foundry factories (no shared fixture reliance).
3. If locale-sensitive => add data provider `[['fi'], ['en']]`.
4. Assertions:
   - Use `assertResponseIsSuccessful()`
   - Use `assertSelectorExists()` or `assertSelectorTextContains()`
   - Avoid raw HTML scanning; add `data-test` hooks if needed.
5. Negative scenario:
   - At least one invalid input / access denial variant.
6. No environment variable mutation; prefer service overrides or configuration.

---

## 12. Service Overrides (Test Performance & Determinism)

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

## 13. Static Analysis Generics Task (31D / 31E)

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

## 14. Mutation + Static Analysis Synergy

Use mutation survivors to inform missing unit tests:
- Escaped conditional mutants => Add focused unit test on branch logic.
- Recurrent repository logic escapes => Add repository tests with precise fixtures/factories.

Static analysis hints for mutation:
- Missing generics => Poor inference => Harder to write tight tests.

---

## 15. Decision Log Integration

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

## 16. Common Pitfalls (Avoid)

| Pitfall                          | Replace With |
|----------------------------------|--------------|
| Broad substring HTML checks      | Selector or `data-test` attributes |
| Reusing cross-test DB state      | Factory creation per test |
| Silent new ignore in phpstan.neon| Document + add expiration |
| Adding new global fixtures       | Factory or targeted minimal fixture |
| Overly generic assertions        | Structural invariants (exact selector presence) |
| Duplicated locale tests          | Data providers |

---

## 17. Quick Command Cheatsheet (Copy/Paste)

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

## 18. Extending This Document

If adding a new tooling layer:
1. Add section with purpose + canonical commands.
2. Reference any configuration files introduced.
3. Update test writing checklist if it changes developer ergonomics.

---

## 19. Assistance Behavior Summary (For AI Agent)

When asked to:
- “Add test”: propose factory-driven, locale-aware, structural assertions.
- “Fix failing test”: inspect isolation assumptions & factory correctness before altering assertions.
- “Improve performance”: suggest parallelization AFTER confirming no shared temp dirs / cross-test global writes.
- “Handle static analysis noise”: group by category, resolve in priority order, avoid knee-jerk ignores.

Never:
- Delete history in metrics.
- Introduce broad `ignoreErrors` blocks.
- Replace structural assertions with loose substrings for convenience.
- Hardcode secrets or environment credentials.

---

### 21.1 Site-Aware Client Requirement (Sonata Page Multisite)

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

## 20. Open TODO Interlocks (Select Highlights)

| Task ID | Dependency / Precondition |
|---------|---------------------------|
| 31D     | Needed before deeper PHPStan cleanup |
| 31E     | Requires 31D complete |
| I       | Mutation baseline (some negative tests already in place) |
| E       | Finish substring purge (ProfileEditLocaleTest residual) |
| J       | Documentation expansion (will reference this CLAUDE.md) |

---

## 21. Contact Points (Conceptual)

If an entity type is unknown for a new Admin class:
- Temporarily use `<object>` generic but raise a task to replace it.
If an external service appears in a test:
- Replace with null / fake transport and document override.

---

End of CLAUDE.md
