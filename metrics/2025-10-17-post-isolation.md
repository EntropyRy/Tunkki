Tunkki-dev/metrics/2025-10-17-post-isolation.md
# Metrics Snapshot: Post-Isolation (2025-10-17)

**Date:** 2025-10-17  
**Commit:** 283170290a727386bf66c4b4e8edd945ca460410  
**Context:** Post-transactional isolation, Foundry factory adoption, substring assertion purge, and partial mutation baseline.

---

## Test Suite Summary

- **Total tests:** 467
- **Assertions:** 2660
- **Runtime:** 00:36.746 (wall clock)
- **Coverage:**  
  - Lines: 39.11%  
  - Methods: 49.12%  
  - Classes: 15.23%

### Isolation & Factory Adoption

- Transactional DB isolation via DAMADoctrineTestBundle (`use_savepoints: true`)
- Static EntityManager removed; per-test `$this->em` pattern
- Foundry factories adopted for User, Member, Event, Artist
- Legacy broad fixtures removed from event-centric tests
- CMS baseline seeding now story-based and idempotent

### Assertion Strategy

- Substring assertions purged (except documented meta tag exceptions)
- Structural assertions via `assertSelectorExists`, `assertResponseIsSuccessful`, etc.
- Redirect assertions standardized (`assertResponseRedirects`)

### Locale & Routing

- Bilingual admin route acceptance tests present and stable
- Locale switching tests use data providers
- SiteAwareKernelBrowser mandated for functional tests

### Mutation & Static Analysis

- Initial Infection run performed (FILTER=src/Security)
- PHPStan generics annotations completed (level=5)
- Post-generics PHPStan triage snapshot pending

---

## Notable Structural Changes

- Transactional rollback isolation for functional/integration tests
- Foundry factories replace legacy fixtures for most domains
- CMS baseline seeding now deterministic and parallel-safe
- Substring assertion purge completed
- Redirect assertion migration completed
- Static analysis generics annotation (31D) completed

---

## Next Focus

- Complete semantic state catalog and micro unit tests for factories
- Finalize reusable test user trait and adopt across suite
- Remove ensureOpenEntityManager after nullability batch 2
- Populate phpstan-triage.md counts post-MT37 batch 2
- Harden parallel execution for CMS-dependent tests
- Expand mutation baseline and survivors table
- Document negative coverage policy and temporal boundary harnesses in TESTING.md

---

## Historical Reference

- Previous baseline: 2025-10-02-baseline.md
- Previous post-isolation: 2025-10-02-post-isolation.md
- Latest slice: 2025-10-08-post-slice.md

---

## Notes

- All metrics snapshots are append-only; do not rewrite historical entries.
- Update this file with actual test/coverage/assertion counts after next full suite run.
- Document any new structural decisions in todo.md Decision Log and mirror summary here.

---