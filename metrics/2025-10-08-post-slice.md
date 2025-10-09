# Test Suite Metrics – Post-Slice Snapshot (Placeholder)
Date: 2025-10-08
Commit: (fill short SHA on commit)
Scope: Post-slice after adding new baselines, PHPStan snapshot, and minor factory hardening

This file captures the “post-slice” state so we can compare against the 2025-10-02 baseline and the post-isolation snapshot. Treat this file as append-only; do not rewrite historical snapshots.

--------------------------------------------------------------------------------
## 1) What changed in this slice

Completed:
- Added per-slice Infection mutation metrics to metrics/mutation-baseline.md (2025-10-08 section).
- Created PHPStan metrics snapshot metrics/phpstan-initial.md (category counts, plan).
- Hardened EventFactory date handling (coerced DateTimeInterface to DateTimeImmutable before calling modify) to address analysis noise in factories.

Planned (next slice):
- Continue PHPStan remediation starting with generics (@extends) and nullability alignment in entities and repositories.
- Add Security unit tests and Twig coverage to address zero MSI slices before tuning Infection timeouts.

--------------------------------------------------------------------------------
## 2) Test Suite (placeholder, to be filled after next run)

Status: Use the commands below to refresh metrics; fill in concrete numbers.

- Total tests: (TBD)  — previously 133 (2025-10-02)
- Assertions: (TBD)    — previously 655 (post-isolation)
- Wall runtime: (TBD)  — previously ~32.4s (post-isolation)
- Coverage lines/methods/classes: (TBD)
- Notable behavioral changes since 2025-10-02: None intentional in this slice

Reproduce:
- Run inside FPM container via provided make targets:
  - make test
  - make coverage (serial preferred)
  - make test-functional / make test-unit as needed

--------------------------------------------------------------------------------
## 3) Mutation Testing (Infection) – 2025-10-08 per-slice summary

Environment: PHP 8.4 (container), PCOV enabled
Flags: --min-msi=0 --min-covered-msi=0, threads=8

- src/Security
  - 64 generated; 0 killed; 58 not covered; 6 required more time
  - MSI 0%; Mutation Code Coverage 0%; Covered MSI 0%
  - Implication: Add unit tests for EmailVerifier and MattermostAuthenticator.

- src/Repository (full slice)
  - 307 generated; 80 killed; 140 not covered; 87 required more time
  - MSI 36%; Mutation Code Coverage 36%; Covered MSI 100%

- src/Repository (only-covered)
  - 167 generated; 75 killed; 0 not covered; 92 required more time
  - MSI 100%; Mutation Code Coverage 100%; Covered MSI 100%

- src/Domain (only-covered)
  - 20 generated; 12 killed; 0 not covered; 8 required more time
  - MSI 100%; Mutation Code Coverage 100%; Covered MSI 100%

- src/PageService (only-covered)
  - 67 generated; 35 killed; 0 not covered; 32 required more time
  - MSI 100%; Mutation Code Coverage 100%; Covered MSI 100%

- src/Twig (only-covered)
  - 173 generated; 0 killed; 0 not covered; 173 required more time
  - MSI 0%; Mutation Code Coverage 0%; Covered MSI 0%
  - Implication: Add targeted Twig tests before increasing timeouts.

- src/Entity (only-covered)
  - 557 generated; 207 killed; 10 covered survivors; 340 required more time
  - MSI 95%; Mutation Code Coverage 100%; Covered MSI 95%
  - Survivors: Focus around Event time-window boundaries and URL construction.

Actions flowing from the above:
- Add ±1s boundary tests for Event publication and window logic; add fi/en URL behavior tests.
- Seed initial Security unit tests to eliminate 0% MSI.
- Introduce Twig coverage to turn “required more time” into actionable results.

--------------------------------------------------------------------------------
## 4) Static Analysis (PHPStan) – 2025-10-08 initial snapshot

Source: metrics/phpstan-report.json

Totals:
- Global errors: 0 (tool-level)
- File errors (messages): 476

Top categories (count → identifier):
- 96 → doctrine.columnType (property type vs ORM mismatch; nullability and DateTimeImmutable consistency)
- 95 → function.alreadyNarrowedType (redundant method_exists/instanceof on known types)
- 88 → missingType.iterableValue (@method docblocks lack array value types)
- 36 → class.notFound (Foundry proxies and other symbols not discovered)
- 35 → missingType.generics (repositories/admins missing @extends T annotations)
- 33 → doctrine.findOneByArgument (invalid criteria fields on Sonata entities)
- 27 → doctrine.associationType (collection/association typing)
- 23 → offsetAssign.valueType (wrong element type into typed collections)
- 13 → varTag.nativeType (PHPDoc vs native type conflicts)
- 11 → argument.type (parameter type mismatches)
- 5 → assign.propertyType (assigning incompatible types)
- 4 → method.notFound (e.g., DateTimeInterface::modify static type)
- Others low frequency (if.alwaysTrue, return.type, parse errors, etc.)

Immediate remediation focus (aligned with CLAUDE.md policy):
1) Generics: add @extends …<Entity> to all repositories/admins; fix @method array value types.
2) Nullability/temporal: align ORM mapping with PHP types; prefer DateTimeImmutable; fix entity temporal properties.
3) Criteria correctness: replace invalid findOneBy/findBy criteria against Sonata entities with valid fields or QueryBuilder.
4) Remove redundant guards: drop method_exists checks on concrete types.
5) Collections: constrain Collection<T> and ensure add/remove methods maintain typed invariants.
6) Boundary mismatches: normalize interfaces (e.g., Security UserInterface vs App\Entity\User).

Note: EventFactory was hardened to avoid calling modify on non-immutable DateTime by coercing to DateTimeImmutable. Remaining factory/static analysis edge cases (e.g., HappeningFactory’s EventFactory parameterization) to be addressed in the next slice.

--------------------------------------------------------------------------------
## 5) Risks & Watchpoints

- Transactional tests can mask flush-order issues → plan a nightly non-transactional run (separate phpunit config).
- Zero MSI slices (Security, Twig) can mislead overall MSI → add targeted tests before adjusting Infection timeouts.
- Widespread entity nullability mismatches → coordinate changes with factories to avoid failing tests during migration.

--------------------------------------------------------------------------------
## 6) Next Actions (ordered)

1) PHPStan: Generics sweep (repositories/admins) + docblock value types for findBy/findOneBy.
2) PHPStan: Nullability/temporal batch (DateTimeImmutable, nullable flags) across entities with highest mismatch counts.
3) Tests: Add Security unit tests (EmailVerifier, MattermostAuthenticator).
4) Tests: Add Event boundary unit tests (+/−1s) and URL-by-locale behavior tests.
5) Tests: Add minimal Twig tests to unlock Infection signal for src/Twig.
6) Rerun:
   - make stan-json (refresh metrics/phpstan-report.json)
   - make infection FILTER=... INFECTION_THREADS=8
   - make test / make coverage (serial)

Record new snapshots:
- Append Infection deltas to metrics/mutation-baseline.md
- Create metrics/YYYY-MM-DD-post-slice.md (this file’s successor)
- Update metrics/phpstan-initial.md with a new dated delta section (append-only)

--------------------------------------------------------------------------------
## 7) Reproduction (authoritative commands)

Run inside the Docker FPM container via make targets:
- Static analysis:
  - make stan-fast
  - make stan
  - make stan-json (writes metrics/phpstan-report.json)
- Tests:
  - make test
  - make test-functional
  - make test-unit
  - make coverage (serial)
- Mutation:
  - make infection INFECTION_THREADS=8 FILTER=src/Security
  - make infection INFECTION_THREADS=8 FILTER=src/Twig --only-covered
  - make infection-baseline (append results to metrics/mutation-baseline.md)

--------------------------------------------------------------------------------
## 8) Append-only Policy

- Do not modify earlier dated sections or snapshots.
- Create a new metrics/YYYY-MM-DD-*.md file for the next slice and reference deltas against this snapshot.

(End of post-slice snapshot – 2025-10-08)