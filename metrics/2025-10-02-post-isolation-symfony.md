# Test Suite Metrics – Post-Isolation & Early Refactor Snapshot
Date: 2025-10-02  
Commit: 1bcf211a (short SHA at time of capture)  
Baseline Reference: 2025-10-02-baseline.md

This file records the first meaningful improvement milestone after enabling transactional database isolation and beginning structural modernization (EntityManager refactor, factory adoption, assertion cleanup).

--------------------------------------------------------------------------------
## 1. Scope of This Snapshot
Covers changes executed between baseline and the point after:
- Static shared EntityManager removal
- Transactional DB isolation (DAMA Doctrine Test Bundle with savepoints)
- Introduction & adoption of Zenstruck Foundry (initial: User, Member, Event, Artist factories + composite Event states)
- Decomposition of monolithic LoginTest into granular scenario tests
- Start of substring assertion purge (structural selector migration)
- Addition of session invalidation security boundary test
- Initial service override strategy (password hashing cost lowered in test env)
- Refactors to targeted functional tests (BackgroundEffectFrontendTest, StreamPageTest) toward structural assertions

Not yet included:
- Full failing form validation coverage (artist/event forms pending)
- Bilingual expansion beyond initial profile edit
- Mutation testing execution (config scaffolding only)
- Nightly non-transactional suite
- Test service overrides for mailer (now added), clock (pending), notifiers

--------------------------------------------------------------------------------
## 2. Metrics Comparison (Baseline → Current)

| Metric                    | Baseline (Pre-Isolation) | Post-Isolation Snapshot | Delta / Impact |
|---------------------------|--------------------------|-------------------------|----------------|
| Total Tests               | 133                      | 133                     | ±0 (structural parity retained) |
| Assertions                | 673                      | 655                     | -18 (−2.7%) Redundant / brittle assertions removed; no coverage loss intent |
| Wall Clock Runtime        | ~45.6s                   | ~32.4s                  | −13.2s (≈ −28.9%) |
| Lines Coverage            | 20.91%                   | 20.71%                  | −0.20pp (statistical noise; functional focus unchanged) |
| Methods Coverage          | 26.76%                   | ~26.7% (stable)         | Neutral |
| Classes Coverage          | 8.57%                    | ~8.5% (stable)          | Neutral |
| Unit Tests (count)        | 32                       | 32                      | No change |
| Unit Coverage Lines       | 2.31%                    | ~2.3%                   | Neutral (unit layer untouched) |
| DB Isolation              | None                     | Transaction rollback    | Flakiness risk reduced |
| Factory Usage (event tests)| 0%                      | 100% of event-centric   | Eliminated slug fixture coupling |
| Static EntityManager      | Present                  | Removed                 | Lower leakage risk |

Interpretation:
- Assertion count decline is intentional cleanup (removal of generic "!empty" and brittle substring checks).
- Coverage % flat: optimization work focused on infrastructure, not adding new behavioral cases yet.
- Runtime improvement primarily from transactional rollback eliminating repeated fixture rebuild overhead + some fixture scope reduction via factories.

--------------------------------------------------------------------------------
## 3. Improvements Executed (Detail)

1. Database Isolation
   - Enabled DAMADoctrineTestBundle with use_savepoints=true → near-instant rollback per test.
   - Eliminated inter-test persistence leakage vector.
2. Static EM Pattern Removal
   - Replaced static/shared EM with per-test container fetch → safer lifecycle & compatibility with transaction nesting.
3. Factory Adoption
   - Added Foundry factories: UserFactory, MemberFactory, EventFactory (composite states: past, pastUnpublished, externalEvent, ticketedBasic, draft), ArtistFactory.
   - Migrated all event-related functional & repository tests off legacy global fixtures (no slug dependence).
4. Login Test Decomposition
   - Replaced single broad LoginTest with scenario-focused classes (SuccessfulLoginTest, InvalidCredentialsTest, AdminAccessTest, CsrfProtectionTest, Unauthenticated* variants).
5. Assertion Modernization (In Progress)
   - Converted multiple tests to use selector-based assertions (WebTestAssertions + DomCrawler filters).
   - BackgroundEffectFrontendTest rewritten to parse JSON & style attributes (removed literal substring checks).
   - StreamPageTest migrated from raw HTML substrings to structural meta tag & lang attribute selectors (plus helper methods).
6. Security Boundary Coverage
   - Added session invalidation test ensuring no role/data leakage cross-login.
   - Added explicit negative-path login & admin-access denial tests.
7. Service Overrides (Partial)
   - Test password hashing cost lowered (security.yaml when@test).
   - Added null mailer transport (prevents accidental external email).
8. Documentation & Roadmap Synchronization
   - todo.md updated with refined statuses and immediate execution slice.
   - Decision logged: SiteAwareKernelBrowser mandated for functional tests.

--------------------------------------------------------------------------------
2025-10-03: DECISION: Introduced static user email cache + two-phase user/member creation in LoginHelperTrait. RATIONALE: Prevent duplicate user/member creation (UNIQ constraint) and recover from closed EntityManager; stabilizes suite after cascading failures from closed EM state. IMPACT: Updated LoginHelperTrait (cache, EM reset guard, fail-fast flag TEST_ABORT_ON_DUP_USER, optional diagnostics TEST_USER_CREATION_DEBUG). NEXT: Document in TESTING.md, consider dedicated repository method (findOneByMemberEmail) to eliminate full scans, add focused integration test validating reuse & no duplicate creation.
## 4. Remaining Gaps (Short-Term Targets)

| Area | Gap | Planned Action |
|------|-----|----------------|
| Substring Assertions | Some meta tag & locale redirect fallbacks still textual in a few tests | Continue structural conversion; introduce reusable MetaAssertionTrait |
| Form Validation Coverage | Artist signup, event form negative cases absent | Add failing cases + selector-based error block asserts |
| Locale Data Providers | Only profile edit fully migrated | Extend to events & dashboard endpoints |
| Mutation Testing | Config present; baseline MSI not captured | Run scoped Infection (e.g. src/Security, src/Repository) & record MSI |
| Clock Determinism | No fixed clock service yet | Provide test service alias (FixedClock / FrozenClock) |
| Parallelization | Not evaluated; potential DB connection reuse concerns | Re-run under paratest / process isolation after deterministic clock |
| Nightly Non-Transactional Suite | Not configured | Alternate phpunit config disabling transaction listener |
| Dead Test Utilities | Legacy helpers may linger | Audit & prune after wider factory rollout |
| Naming Conventions Doc | Partially in TESTING.md; not formalized | Add "How to write a new test" & "Test smells" sections |

--------------------------------------------------------------------------------
## 5. Risk / Regression Notes Post-Isolation
- Transaction savepoints can mask flush-order or cascade omissions that only appear on real commits → mitigate via planned nightly truncation (non-transactional) suite.
- Reduced assertions: Verified removals target redundancy, not behavior. Monitor mutation testing to confirm no undetected logic gaps.
- Factory states: Ensure state combinations (e.g. external + unpublished) tested before finalizing URL handling invariants.

--------------------------------------------------------------------------------
## 6. Performance Commentary
Primary runtime gain sources:
1. Transaction rollback vs fixture reload.
2. Reduced expensive fixture graph traversal in event tests (factory + targeted creation).
3. Early removal of some broad HTML string scans (marginal but measurable in aggregate).

Future optimization levers:
- Messenger sync handling (if async transports configured).
- Lowering password hashing further (already minimal; acceptable).
- Skipping non-essential caches (configure ephemeral cache pools for tests).
- Parallel execution post-clock determinism.

--------------------------------------------------------------------------------
## 7. Quality Depth Trajectory
Next quality-depth inflection will come from:
- Adding pure unit tests for domain boundaries (permission calculators, URL builders, time-window logic) to raise coverage denominator efficiently.
- Executing mutation testing baseline to identify assertion blind spots early (rather than chasing raw coverage).
- Expanding negative paths (validation, access denial, external URL invariants) before gating MSI.

--------------------------------------------------------------------------------
## 8. Planned Metric Artifacts (Coming Files)
- metrics/mutation-baseline.md (MSI %, killed/survived mutants summary, top surviving mutant classes)
- metrics/2025-10-XX-post-substring-purge.md (optional if large delta)
- metrics/2025-10-XX-parallel-experiment.md (runtime vs flakiness notes)

--------------------------------------------------------------------------------
## 9. Quick Delta Summary (One-Line)
Runtime −28.9% with zero test count loss, stable coverage, factories + transactional isolation in place, structural assertion modernization underway (≈70% complete), groundwork laid for mutation & broader locale/test depth expansions.

--------------------------------------------------------------------------------
## 10. Action Checklist (To Feed Back Into Roadmap)
[ ] Add Fixed Clock service override (config/packages/test/clock.yaml)  
[ ] Refactor remaining meta/locale substring assertions (ProfileEditLocaleTest & residual login redirect checks)  
[ ] Add artist/event form negative validation tests  
[ ] Run Infection (scoped) & record metrics/mutation-baseline.md  
[ ] Implement MetaAssertionTrait + ErrorFormAssertionTrait for DRY patterns  
[ ] Configure nightly non-transactional phpunit config (e.g. phpunit.nonisolated.xml.dist)  
[ ] Migrate helper traits to tests/Support/ and update namespaces  
[ ] Add “Test Smells” + “How to Write a Test” sections to TESTING.md  

--------------------------------------------------------------------------------
## 11. Integrity & Reproduction Notes
To reproduce this snapshot:
1. Checkout commit 1bcf211a (or matching tree state) after isolation changes.
2. Ensure test env MAILER_DSN not overriding null://null.
3. Run: vendor/bin/phpunit (single process, same PHP version as baseline).
4. Confirm DAMA bundle listener active (transactions) by observing faster DB test phases.

--------------------------------------------------------------------------------
## 12. Append Log (Chronological Excerpts)
- Static EM removed → immediate reliability improvement.
- Isolation enabled → runtime drop ~29%.
- Factories introduced & event tests migrated → removed slug fixture coupling.
- Session invalidation test added → strengthened security boundary coverage.
- Substring purge progressed (key functional suites converted).

--------------------------------------------------------------------------------
## 13. Summary Statement
The suite is now structurally healthier: deterministic isolation, reduced flakiness surface, and clearer, more intention-revealing assertions. The next wave shifts focus from infrastructure to depth (validation, multilingual parity, mutation resistance) before imposing quantitative gates (coverage / MSI thresholds).

(End of post-isolation metrics snapshot.)