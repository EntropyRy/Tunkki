# Test Suite Metrics Baseline
Date: 2025-10-02
Commit: 1bcf211a (short SHA)  (If this changes before commit, update here.)

Purpose:
Establish an authoritative pre-refactor snapshot for performance, breadth, and coverage prior to (a) transactional DB isolation, (b) factory migration, and (c) assertion modernization. This file remains immutable—subsequent improvements go into separate metrics files (e.g. 2025-10-02-post-isolation.md, mutation-baseline.md).

--------------------------------------------------------------------------------
## 1. Environment Snapshot (Logical)
- PHP: 8.4 (constraint ^8.4 in composer.json; exact runtime version not recorded here)
- Symfony: ~7.1 (framework-bundle ^7.1)
- ORM: doctrine/orm ^3.1
- Test libs: phpunit/phpunit ^12.3, dama/doctrine-test-bundle (not yet enabled for this baseline), zenstruck/foundry (planned; not active for legacy tests at this baseline)
- Coverage engine: PCOV (lines, methods, classes captured; no branch coverage)
- Mutation testing: Not yet executed (Infection config not applied at this point)

NOTE: No parallelization; single-process run (wall clock recorded manually).

--------------------------------------------------------------------------------
## 2. Unit Test Suite Baseline (Isolated)
Scope: Tests tagged / organized as pure unit (no kernel boot, minimal framework interaction)

Metrics:
- Tests: 32
- Assertions: 153
- Reported Runtime (PHPUnit): ~0.23s
- Coverage (Unit-only pass):
  - Lines: 2.31%
  - Methods: 2.81%
  - Classes: 0.95%

Observations:
- Extremely low direct unit coverage—most behavioral assurance lives in functional/integration tests.
- Opportunity: Extract pure domain logic (URL generation, permission checks, time window logic) to raise signal density without kernel cost.

--------------------------------------------------------------------------------
## 3. Full Suite Baseline (Unit + Functional + Integration)
Metrics:
- Tests: 133
- Assertions: 673
- Wall Clock Runtime: ~45.6s
- Coverage (aggregate):
  - Lines: 20.91%
  - Methods: 26.76%
  - Classes: 8.57%

Observations:
- Runtime dominated by functional tests performing DB work + broad fixture loading.
- Assertion volume includes several brittle substring checks and generic fallback assertions (targeted for replacement).
- Static shared EntityManager pattern still in place at this baseline.
- Global fixtures used for event- and member-related scenarios (pre-factory migration).

--------------------------------------------------------------------------------
## 4. Known Baseline Anti-Patterns
(These are intentionally left unchanged at baseline for comparative improvement measurement.)

| Category                  | Baseline State (Pre-Refactor)                                      |
|--------------------------|---------------------------------------------------------------------|
| DB Isolation             | No per-test transactional rollback (fixtures reused globally)       |
| Entity Creation          | Reliance on broad legacy fixtures; no Foundry factories in use      |
| Assertions               | Frequent assertStringContainsString on raw HTML                     |
| Authentication Setup     | Manual POST /login sequences; repetitive setup code                 |
| Redirect Handling        | Some loop-based followRedirect patterns                             |
| Env Mutation             | Legacy test mutated env vars (e.g. TEST_DEBUG_LOGIN)                |
| Static Services          | Static EntityManager usage pattern in FixturesWebTestCase           |
| Coverage Depth           | Limited unit-level coverage; functional tests shoulder burden       |
| Performance Overhead     | Unnecessary fixture hydration for scenarios needing only a subset   |

--------------------------------------------------------------------------------
## 5. Initial Risk Notes
- Cross-test data leakage possible (no isolation) → risk of order-dependent flakiness.
- Mutation testing not yet run—unknown survivability of logic mutants.
- Low unit coverage implies refactors could silently reduce logic correctness if functional tests miss paths.

--------------------------------------------------------------------------------
## 6. Planned Immediate Improvements (Next Files Will Reflect)
Reference (not executed yet in this baseline):
1. Introduce transactional isolation via dama/doctrine-test-bundle.
2. Introduce Zenstruck Foundry & migrate high-churn tests off global fixtures.
3. Replace brittle assertions with structural (selector-based) assertions.
4. Split oversized scenario tests (notably legacy LoginTest) into focused cases.
5. Add negative path & security boundary coverage to improve mutation survivability.
6. Scaffold mutation testing (Infection) and record MSI separately.

--------------------------------------------------------------------------------
## 7. Interpretation Guidelines
- Do NOT compare future coverage % directly unless the file set (src/) remains stable; structural refactors may shift denominator.
- Track improvement deltas in subsequent metrics files rather than editing this baseline.
- If future runtime deviates upward, correlate with factory seeding strategy or added I/O mocks.

--------------------------------------------------------------------------------
## 8. Integrity / Reproducibility
To reproduce baseline:
- Use the commit hash above BEFORE enabling DAMADoctrineTestBundle.
- Ensure no test-specific service overrides (null mailer, clock) are active beyond defaults.
- Run: vendor/bin/phpunit (or project’s ./ci/test.sh) with coverage instrumentation identical to original invocation.

--------------------------------------------------------------------------------
## 9. Follow-Up Recording Checklist (For Post-Isolation File)
When creating 2025-10-02-post-isolation.md include:
[ ] New test count & assertion count
[ ] New wall clock runtime
[ ] Coverage deltas
[ ] Notable structural changes (EntityManager pattern removal, factory adoption scope)
[ ] Any flakiness reduction notes (if observed)
[ ] Preliminary list of remaining substring assertions (if any)

--------------------------------------------------------------------------------
## 10. Baseline Summary (One-Line)
45.6s / 133 tests / 673 assertions / 20.91% line coverage with legacy fixtures, static EM, no DB isolation, minimal unit coverage.

(End of baseline file – immutable snapshot.)