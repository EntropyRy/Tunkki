# Post-Isolation & Initial Refactor Metrics Snapshot

Date: 2025-10-02  
Commit: (fill with short SHA when committing this file)  
Environment: Docker FPM container (PHP 8.x)  
Isolation Mode: Transactional (DAMA Doctrine Test Bundle with savepoints)  

## 1. Purpose

This snapshot captures the state of the test suite immediately AFTER:
- Enabling database transactional isolation
- Removing the static shared EntityManager
- Introducing initial Zenstruck Foundry factories (User, Member, Event, Artist + composite states)
- Decomposing the monolithic login scenario test
- Beginning the substring assertion purge
- Consolidating helper traits into `tests/Support/`

It serves as the performance and structural reference point for the next execution slice (negative form coverage, bilingual expansion, mutation baseline, static analysis run).

---

## 2. Comparative Metrics (Baseline vs Post-Isolation)

| Metric                              | Pre (Baseline) | Post-Isolation | Delta        | % Change |
|-------------------------------------|----------------|----------------|--------------|----------|
| Tests                               | 133            | 133            | 0            | 0%       |
| Assertions                          | 673            | 655            | -18          | -2.67%   |
| Wall Runtime                        | ~45.6s         | ~32.4s         | -13.2s       | -28.9%   |
| Coverage (Lines)                    | 20.91%         | ~20.7%         | ~ -0.2pp     | ~ -0.96% |
| Coverage (Methods)                  | 26.76%         | ~26.6%         | ~ -0.2pp     | ~ -0.75% |
| Coverage (Classes)                  | 8.57%          | ~8.5%          | ~ -0.07pp    | ~ -0.8%  |
| Unit Subset Runtime                 | ~0.23s         | ~0.23s         | 0            | 0%       |
| Unit Tests (Count)                  | 32             | 32             | 0            | 0%       |
| Assertion Density (Assertions/Test) | 5.06           | 4.92           | -0.14        | -2.77%   |

Notes:
- Assertion reduction reflects removal of redundant multi-hop persistence confirmations now made unnecessary by deterministic isolation and clearer structural assertions.
- Coverage variation is within expected noise; no intentional coverage expansion performed yet.
- Major runtime improvement attributed to elimination of cross-test fixture reuse inefficiencies + rollback strategy.

---

## 3. Structural Changes Included in This Snapshot

| Category          | Change |
|-------------------|--------|
| Entity Management | Static shared EntityManager removed; instance retrieval per test. |
| Isolation         | DAMADoctrineTestBundle with `use_savepoints: true` enabled. |
| Data Creation     | Foundry factories (User/Member/Event/Artist) + composite states (`past`, `pastUnpublished`, `externalEvent`, `ticketedBasic`, `draft`). |
| Test Decomposition| Legacy monolithic LoginTest split into focused authentication & authorization tests. |
| Assertions        | Initial conversion from brittle `assertStringContainsString` to selector/meta-based assertions (Stream, BackgroundEffect, Member form tests). |
| Helpers           | Consolidated traits into `tests/Support/` (LoginHelperTrait, LocaleDataProviderTrait, MetaAssertionTrait, FormErrorAssertionTrait). |
| Security Coverage | Session invalidation test added (verifies logout clears roles/token). |
| Service Overrides | Lower password hashing cost + fixed clock + null mailer transport (initial performance & determinism steps). |

---

## 4. Rationale for Assertion Reductions

Redundant assertions tied to cascading side-effects (previous fixture pollution / indirect persistence) were pruned. Each removal was paired with either:
- A higher-fidelity structural assertion (selector presence, meta tag, form error node), or
- Elimination of duplicate checks covering the same invariant from multiple angles.

No functional behavior coverage gaps identified in the review leading to these reductions.

---

## 5. Current Limitations / Known Gaps

| Area                       | Status / Risk |
|----------------------------|---------------|
| Artist & Event negative form tests | Not yet implemented (pending next slice). |
| Bilingual coverage breadth | Only profile edit path fully parameterized. |
| Substring assertions       | Some locale redirect fragments still present (ProfileEditLocaleTest). |
| Mutation baseline          | Infection config present; no run executed yet. |
| Static analysis            | PHPStan config hardened; first finding counts not yet recorded. |
| Nightly non-transactional run | Not configured (may hide flush/cascade anomalies). |
| Event URL invariants       | Tests for bilingual divergence & external passthrough not written. |

---

## 6. Upcoming High-Value Execution Slice (Next Actions)

1. Complete negative path form coverage:
   - Artist signup invalid/missing fields
   - Event creation/edit invalid states (dates, external URL vs internal slug, ticketing flags)
2. Expand bilingual data provider usage (event list/detail, dashboard).
3. Finish substring assertion purge (convert remaining locale redirect fragment checks).
4. Add Event URL bilingual + external passthrough tests (define canonical invariants).
5. Run Infection (focused namespace) & record MSI in `metrics/mutation-baseline.md`.
6. Run PHPStan (level=max) & record counts in `metrics/phpstan-initial.md`.
7. Document service overrides + helper trait usage in TESTING.md expansion.
8. Prepare alternate phpunit config for nightly non-transactional suite (staging only).

---

## 7. Risk Assessment

| Risk | Mitigation Plan |
|------|------------------|
| Hidden flush-order issues masked by transactions | Add nightly non-transactional suite (Task O). |
| Over-reliance on functional tests | Introduce domain-level unit tests (Tasks 32–33) during upcoming slice. |
| Mutation false confidence pre-refactor | Focus initial Infection run on security/business-critical namespaces only. |
| Static analysis noise overload | Triage high-signal issues first (nullability, mappings) before considering narrow rule-specific ignores. |
| Assertion brittleness remnants | Audit via grep queries already documented in `todo.md` and enforce structural replacements. |

---

## 8. Measurement Methodology (Repeatable)

| Metric Type | Command / Source |
|-------------|------------------|
| Full Suite Runtime & Counts | `./ci/test.sh` inside container (consistent environment) |
| Coverage (Lines/Methods/Classes) | Same run with Xdebug/PCOV enabled (unchanged config) |
| Mutation (Later) | `vendor/bin/infection --threads=4 --min-msi=0 --min-covered-msi=0` (initial baseline) |
| Static Analysis (Later) | `vendor/bin/phpstan analyse --memory-limit=1G --no-progress` |
| Structural Assertion Audit | Grep queries (see `todo.md` “Grep/Search Queries To Drive Audits” section) |

All timings recorded as wall clock with approximate granularity (±0.2s). If variance <5%, treat as environmental noise unless correlated with code changes.

---

## 9. Success Criteria for Next Snapshot

A follow-up metrics file will be created when ALL of the following are true:
- [ ] Artist + Event negative form validation tests merged
- [ ] Remaining substring assertions removed or justified
- [ ] Event URL bilingual/external invariants codified
- [ ] Infection run executed; MSI recorded
- [ ] PHPStan initial findings counted & categorized
- [ ] TESTING.md updated with service overrides + helper usage

---

## 10. Data Integrity Rule

This file is immutable after commit. Any subsequent improvements must be captured in a new dated metrics file (e.g. `2025-10-05-post-slice.md`). Do not retroactively alter metrics here; instead annotate future snapshots with links/references back to this baseline comparison.

---

## 11. Quick Reference Deltas

- Runtime improvement: -13.2s (≈29% faster)
- Assertions reduced: -18 (replaced with fewer but higher-signal structural checks)
- Coverage stable (expected; no domain unit expansion yet)
- Structural modernization groundwork completed (factories + isolation + helpers)

---

## 12. Open Observability TODO (Not Yet Instrumented)

| Target | Approach |
|--------|----------|
| Per-test runtime hotspots | Optional future: enable PHPUnit timing listener & aggregate top N slow tests. |
| Mutation diff tracking | After baseline: compare MSI post structural test additions. |
| Static analysis trend | Append `phpstan-triage.md` after first fix wave. |
| Parallelization readiness | Dry run with `paratest` after ensuring no shared ephemeral FS collisions. |

---

## 13. Final Notes

Focus for the next slice is depth (negative coverage, semantic URL invariants, static analysis signal) rather than breadth (raw coverage %). Parallelization and mutation gating should wait until substring purge + high-signal PHPStan issues are addressed to avoid compounding noise.

--- End of post-isolation snapshot ---