# Mutation Testing Baseline (Infection)

### 2025-10-08 (Baseline – Per-slice Mutation Metrics)
Environment: Docker FPM (PHP 8.4.13), PCOV enabled
Threads: 8
Flags: --min-msi=0 --min-covered-msi=0

- Slice: src/Security
  - Command: vendor/bin/infection --threads=8 --filter=src/Security
  - Mutations: 64 generated; 0 killed; 58 not covered; 0 errors; 0 syntax errors; 0 time outs; 6 required more time
  - Metrics: MSI 0%; Mutation Code Coverage 0%; Covered Code MSI 0%
  - Notes: No coverage; prioritize unit tests for EmailVerifier and MattermostAuthenticator.

- Slice: src/Repository (full slice)
  - Command: vendor/bin/infection --threads=8 --filter=src/Repository
  - Mutations: 307 generated; 80 killed; 140 not covered; 0 covered survivors; 0 errors; 0 syntax errors; 0 time outs; 87 required more time
  - Metrics: MSI 36%; Mutation Code Coverage 36%; Covered Code MSI 100%

- Slice: src/Repository (only-covered)
  - Command: vendor/bin/infection --threads=8 --filter=src/Repository --only-covered --show-mutations
  - Mutations: 167 generated; 75 killed; 0 not covered; 0 covered survivors; 0 errors; 0 syntax errors; 0 time outs; 92 required more time
  - Metrics: MSI 100%; Mutation Code Coverage 100%; Covered Code MSI 100%

- Slice: src/Domain (only-covered)
  - Command: vendor/bin/infection --threads=8 --filter=src/Domain --only-covered --show-mutations
  - Mutations: 20 generated; 12 killed; 0 not covered; 0 covered survivors; 0 errors; 0 syntax errors; 0 time outs; 8 required more time
  - Metrics: MSI 100%; Mutation Code Coverage 100%; Covered Code MSI 100%

- Slice: src/PageService (only-covered)
  - Command: vendor/bin/infection --threads=8 --filter=src/PageService --only-covered --show-mutations
  - Mutations: 67 generated; 35 killed; 0 not covered; 0 covered survivors; 0 errors; 0 syntax errors; 0 time outs; 32 required more time
  - Metrics: MSI 100%; Mutation Code Coverage 100%; Covered Code MSI 100%

- Slice: src/Twig (only-covered)
  - Command: vendor/bin/infection --threads=8 --filter=src/Twig --only-covered --show-mutations
  - Mutations: 173 generated; 0 killed; 0 not covered; 0 covered survivors; 0 errors; 0 syntax errors; 0 time outs; 173 required more time
  - Metrics: MSI 0%; Mutation Code Coverage 0%; Covered Code MSI 0%
  - Notes: Indicates lack of tests over Twig logic; add targeted tests before tuning timeouts.

- Slice: src/Entity (only-covered)
  - Command: vendor/bin/infection --threads=8 --filter=src/Entity --only-covered --show-mutations
  - Mutations: 557 generated; 207 killed; 0 not covered; 10 covered survivors; 0 errors; 0 syntax errors; 0 time outs; 340 required more time
  - Metrics: MSI 95%; Mutation Code Coverage 100%; Covered Code MSI 95%
  - Notes: Survivors focus around Event time-window boundaries and URL construction; add ±1s boundary tests and fi/en URL behavior tests.

Key implications:
- Add Security unit tests (EmailVerifier, MattermostAuthenticator).
- Add a functional test for ArtistController::create to assert member linkage, submitted+valid gate, and Mattermost message content/URLs.
- Address Twig/service slices’ “required more time” via added coverage before tweaking timeouts.


Date: 2025-10-02  
Commit: (fill with short SHA on commit)  
Environment: Docker FPM container (PHP 8.x)  
Infection Config: infection.json.dist (scoped namespaces: (initial) src/Repository, src/Security — adjust if changed)

---

## 1. Purpose

This file records the INITIAL (pre-optimization) mutation testing results so we can:
- Track Mutation Score Indicator (MSI) over time.
- Validate that structural refactors + negative path tests meaningfully kill additional mutants.
- Avoid setting premature thresholds that would block productive refactoring.

DO NOT retroactively edit historical sections; append new dated sections instead.

---

## 2. Execution Command (Reference)

(Inside the PHP FPM container; ensure vendor deps installed)

```
vendor/bin/infection \
  --threads=4 \
  --min-msi=0 \
  --min-covered-msi=0 \
  --ansi \
  --log-verbosity=default
```

If using a narrower namespace to keep runtime low during baseline refinement:

```
vendor/bin/infection --filter=src/Security --threads=4 --min-msi=0 --min-covered-msi=0
```

Optional performance aids:
- Add `--only-covered` after initial full scan if noise is high.
- Use `--skip-initial-tests` ONLY after a reliable PHPUnit coverage cache pattern is established (not recommended for the very first baseline).

---

## 3. Baseline Result (To Be Filled After First Run)

Fill these after the FIRST complete focused run:

| Metric                        | Value | Notes |
|------------------------------|-------|-------|
| Mutants Generated            | (n)   |       |
| Mutants Covered              | (n)   | (ideally high vs generated) |
| Mutants Killed               | (n)   |       |
| Mutants Escaped (Survived)   | (n)   |       |
| Mutants Timed Out            | (n)   | Investigate if persistent |
| Mutants Ignored (Profile)    | (n)   | Due to config (e.g., ignore MSI safe patterns) |
| MSI (Global)                 | (x%)  | Mutation Score Indicator |
| Covered MSI                  | (x%)  | Higher-signal: only covered lines |
| Runtime (Infection)          | (s)   | Wall clock |
| PHPUnit (per initial run)    | (s)   | Ensures baseline comparability |

Copy raw summary output here (verbatim block):

```
(paste Infection summary block)
```

---

## 4. High-Priority Surviving Mutants (Initial Triage)

List only those in business/security logic or critical domain flows:

| File/Class | Mutation Description | Why Important | Planned Kill Strategy |
|------------|----------------------|---------------|-----------------------|
|            |                      |               |                       |

Classification guidance:
- Security: Authentication, authorization, CSRF/token logic
- Domain invariants: Date windows, slug formation, state transitions
- Repository filters: Visibility, published/unpublished, language scoping

---

## 5. Low-Signal / Deferred Mutants

Mutants you consciously defer (document rationale rather than dropping silently):

| File/Class | Mutation | Reason to Defer | Reevaluate When |
|------------|----------|-----------------|-----------------|
|            |          |                 |                 |

Valid reasons:
- Code pending refactor removal
- Transitional adapter layer
- Logging-only branch (consider narrowing config later)

---

## 6. Immediate Follow-Up Actions After Baseline

Check these off as executed:

[ ] Add/strengthen negative tests for escaped control-flow mutants  
[ ] Add edge-case tests for date/time window logic (if flagged)  
[ ] Increase assertions around security response variants (redirect vs deny)  
[ ] Consolidate duplicated logic to make mutants more meaningful  
[ ] Re-run Infection and create next snapshot (append new dated section below)

---

## 7. Exclusion Philosophy (Do This Sparingly)

DO NOT exclude entire directories preemptively.  
Before ignoring:
1. Can a small, focused test kill the mutant?
2. Is the code slated for deletion?
3. Is the mutant in trivial data structure glue? (If so, still consider a micro-test before excluding.)

If exclusion required, justify in a table:

| Pattern/Path | Justification | Expiration Condition |
|--------------|---------------|----------------------|
|              |               |                      |

---

## 8. Coverage vs Mutation Interplay

If a high number of escaped mutants align with untested branches:
- Consider adding unit-level tests first (cheaper than broad functional assertions).
- Avoid padding with trivial assertions; focus on logic forks (if/else, guard clauses, strategy selection).

Mutation testing should guide *quality depth*, not inflate shallow coverage.

---

## 9. Threshold Strategy (Staged)

Do NOT set strict CI thresholds now. Proposed phase gates:

| Stage | Condition | Threshold Action |
|-------|-----------|------------------|
| Baseline (now) | Initial run | No thresholds (`--min-msi=0`) |
| Stage 1 | After killing top 10 high-value mutants | Introduce soft reporting only |
| Stage 2 | Stable MSI > (TBD, e.g. 45–55%) | Add non-blocking warning in CI |
| Stage 3 | Stable MSI > (TBD, e.g. 60–65%) & low noise | Set `--min-msi` to (current - 5%) |
| Stage 4 | Plateau & domain tests strong | Enforce `--min-covered-msi` slightly higher than global |

Document transitions in CHANGELOG / metrics files.

---

## 10. Future Enhancements

Potential improvements after initial cycles:
- Split Infection configs by domain module (faster targeted feedback).
- Use Git diff–aware mutation runs for PRs (speed).
- Track MSI trend in a simple CSV (optional).

Example CSV line (if created later):
```
2025-10-04,<commit>,generated=###,killed=###,escaped=###,msi=##.#,covered_msi=##.#,runtime=##.#
```

---

## 11. Append-Only Historical Sections

Below this line, append new dated sections after each significant mutation iteration.

---

### 2025-10-02 (Baseline)
Status: PENDING (run not executed yet)
Notes: Fill Section 3 once Infection is run; do not alter structure above.

---

### Survivor Triage Placeholder (Pre-Run Section)

(To be appended AFTER the first Infection execution. Leave untouched until baseline metrics in Section 3 are filled.)

#### 1. High-Value Survivor Candidates (Initial Placeholder)

| (Will Be) Class:Line | Mutator | Classification (Security / Domain / Other) | Hypothesis (Why Escaped) | Proposed Test (Unit/Functional) |
|----------------------|---------|--------------------------------------------|--------------------------|---------------------------------|
| (populate)           |         |                                            |                          |                                 |

Guidance:
- Populate only the top 5–8 survivors with highest domain/security impact.
- Skip trivial string / debug-only branches initially.

#### 2. Survivor Prioritization Heuristics

Order of attention:
1. Security boundary (authz/authn) conditional mutations
2. Date / time window logic (signup windows, publication states)
3. Visibility / locale filtering in repositories
4. Slug / URL generation conditionals
5. Fallback / default branch logic in factories or strategy selection

#### 3. Planned Immediate Tests (Post-Baseline)

| Target Class | Survivor Description (Short) | Test Idea Outline | Expected Kill Mechanism | Added In Commit |
|--------------|------------------------------|-------------------|-------------------------|-----------------|
| (populate)   |                              |                   |                         |                 |

#### 4. Deferred Survivors (Document Rationale)

| Class:Line | Reason for Deferral | Revisit Criterion | Possible Refactor |
|------------|---------------------|-------------------|-------------------|
| (populate) |                     |                   |                   |

#### 5. Metrics Delta Template (Fill After MT6)

After first survivor-focused tests land:

```
Delta (YYYY-MM-DD):
- Survivors addressed: <n>
- MSI before:  (x.xx%)
- MSI after:   (y.yy%)
- Covered MSI before: (a.aa%)
- Covered MSI after:  (b.bb%)
Key Kills: ClassA:42 (ConditionRemoval), ClassB:107 (NegateIf)
Follow-up Focus: Remaining security conditional in ClassC, uncovered repository filter branch in ClassD.
```

#### 6. Notes

- Do NOT retroactively edit historical MSI data in Section 3.
- If a survivor becomes obsolete due to refactor/deletion, strike-through in the survivor table and append note “(removed in commit <sha>)”.
- Keep this section lean; move any extended reasoning to a dedicated follow-up snapshot file if it grows beyond a single screen.

---

(End of initial mutation baseline scaffold + survivor triage placeholder)

---

### 2025-10-03 (Preparation – Pre-Run Clarification)

Status: Baseline still pending. Mutation prerequisites completed:
- Proxy misuse eliminated (factories + login helper).
- On-demand CMS baseline seeding (ensureCmsBaseline()) + health test ensures deterministic routing.
- Substring assertion purge complete (MT24 done).
- Ready to execute: make infection FILTER=src/Security inside FPM container.

Next run instructions (do not modify earlier 2025-10-02 section):
1. Run focused baseline:
   vendor/bin/infection --filter=src/Security --threads=4 --min-msi=0 --min-covered-msi=0
2. Paste summary into Section 3 (do not overwrite table headers).
3. Populate High-Priority Surviving Mutants table (Section 4) with top 5–8 only.
4. Add survivor candidates to “High-Value Survivor Candidates” (lines 185–190) after baseline metrics are filled.
5. Plan at least 2 kill tests before expanding scope beyond src/Security.

Planned early survivor focus (expected patterns):
- Authentication failure branches (invalid credentials / CSRF).
- Locale-derived URL divergence (if logic pulled into Security layer).
- Time-window guard logic (if any conditional reachable in security services).

Post-baseline follow-up (to mark in Section 6 checklist):
[ ] Negative branch test additions
[ ] Security redirect vs deny differentiation test
[ ] Artist signup window edge-case (if mutated condition surfaces in Security layer indirectly)

Do not append survivor detail here—reserve that for after first run.

---

### 2025-10-08 (Per-slice Infection Snapshot)

Environment: Docker FPM (PHP 8.4.13), PCOV enabled  
Threads: 8  
General flags: --min-msi=0 --min-covered-msi=0

- Slice: src/Security
  - Command (representative): vendor/bin/infection --threads=8 --filter=src/Security
  - Mutations: 64 generated; 0 killed; 58 not covered; 0 errors; 0 syntax errors; 0 time outs; 6 required more time
  - Metrics: MSI 0%; Mutation Code Coverage 0%; Covered Code MSI 0%

- Slice: src/Repository (full slice)
  - Command: vendor/bin/infection --threads=8 --filter=src/Repository
  - Mutations: 307 generated; 80 killed; 140 not covered; 0 covered survivors; 0 errors; 0 syntax errors; 0 time outs; 87 required more time
  - Metrics: MSI 36%; Mutation Code Coverage 36%; Covered Code MSI 100%

- Slice: src/Repository (only-covered)
  - Command: vendor/bin/infection --threads=8 --filter=src/Repository --only-covered --show-mutations
  - Mutations: 167 generated; 75 killed; 0 not covered; 0 covered survivors; 0 errors; 0 syntax errors; 0 time outs; 92 required more time
  - Metrics: MSI 100%; Mutation Code Coverage 100%; Covered Code MSI 100%

- Slice: src/Domain (only-covered)
  - Command: vendor/bin/infection --threads=8 --filter=src/Domain --only-covered --show-mutations
  - Mutations: 20 generated; 12 killed; 0 not covered; 0 covered survivors; 0 errors; 0 syntax errors; 0 time outs; 8 required more time
  - Metrics: MSI 100%; Mutation Code Coverage 100%; Covered Code MSI 100%

- Slice: src/PageService (only-covered)
  - Command: vendor/bin/infection --threads=8 --filter=src/PageService --only-covered --show-mutations
  - Mutations: 67 generated; 35 killed; 0 not covered; 0 covered survivors; 0 errors; 0 syntax errors; 0 time outs; 32 required more time
  - Metrics: MSI 100%; Mutation Code Coverage 100%; Covered Code MSI 100%

- Slice: src/Twig (only-covered)
  - Command: vendor/bin/infection --threads=8 --filter=src/Twig --only-covered --show-mutations
  - Mutations: 173 generated; 0 killed; 0 not covered; 0 covered survivors; 0 errors; 0 syntax errors; 0 time outs; 173 required more time
  - Metrics: MSI 0%; Mutation Code Coverage 0%; Covered Code MSI 0%

- Slice: src/Entity (only-covered)
  - Command: vendor/bin/infection --threads=8 --filter=src/Entity --only-covered --show-mutations
  - Mutations: 557 generated; 207 killed; 0 not covered; 10 covered survivors; 0 errors; 0 syntax errors; 0 time outs; 340 required more time
  - Metrics: MSI 95%; Mutation Code Coverage 100%; Covered Code MSI 95%

Notes and immediate implications:
- Security slice currently has no coverage; prioritize unit tests for EmailVerifier and MattermostAuthenticator.
- Controller escapes observed (ArtistController create flow); plan a functional test to assert member linkage, strict submitted+valid check, and Mattermost message content/URLs.
- Entity survivors focused in Event time-window boundary logic and URL construction; add focused unit tests for ±1s tolerance and fi/en URL generation.
- Large “required more time” counts in Twig/Service indicate lack of coverage; address with targeted tests before tuning timeouts.

- Update (2025-10-08): Added unit tests for EmailVerifier and MattermostAuthenticator (tests/Unit/Security). Rerun Infection with --filter=src/Security is expected to raise MSI above 0% and produce non-zero Covered MSI. Refresh metrics in this file after the run.

### 2025-10-07 (Attempted Baseline – Blockers)
Status: Baseline still pending. Multiple Infection runs aborted due to the initial test suite not being fully green under Infection’s randomized/default execution.

What was attempted
- Ran make infection FILTER=src/Security several times.
- Tweaked infection.json.dist:
  - Added testFrameworkOptions: --order-by=default --resolve-dependencies
  - All groups now enabled (previous temporary quarantine exclusion removed)
  - Experimented with narrowing suites to Unit using --testsuite=Unit
- Consolidated dashboard access coverage in a focused test (scenario-level duplicate skipped intentionally, not quarantined).
- Made testFindEventBySlugAndYear slug unique to avoid random-order collisions.

Observed blockers (from console logs)
- Infection aborts with “Project tests must be in a passing state …” (PHPUnit exit code 143) during its initial test run.
- Functional admin dashboard tests produce HTTP 500 when executed in Infection’s initial run context (dashboard rendering).
- Earlier failure in EventRepositoryTest::testFindEventBySlugAndYear caused by static slug colliding under randomized order; fixed by using a unique slug.
- StreamRepositoryTest emits diagnostics (“BEGIN stopAllOnline test”); not a failure but confirms Integration tests still run during Infection’s initial phase.

Interim decisions (documented)
- Skipped duplicate scenario-level dashboard reachability (covered by dedicated AdminAccess tests) to reduce flakiness.
- AdminAccessTest restored to normal execution (temporary quarantine removed after stabilizing dashboard rendering).

Next steps to capture baseline
1) Make the initial Infection run deterministic and green:
   - Ensure dashboard block rendering is robust in tests (seed minimal Artist or rely on existing template guard; RandomArtistBlock template already guards null).
   - (Optional) For initial focused debugging, restrict to Unit with --testsuite=Unit; no group exclusions are in use now.
2) Once initial run succeeds:
   - Paste Infection summary into Section 3 “Baseline Result”.
   - Populate Section 4 with top survivors (prioritize Security).
   - Add ≥2 kill tests and re-run a focused subset.

Notes
- Passing --testsuite Unit (with a space) is not supported; use --testsuite=Unit.
- If Infection still executes the full configuration despite testFrameworkOptions, run a one-off:
  vendor/bin/infection --filter=src/Security --test-framework-options="--testsuite=Unit --order-by=default"
  Record the result here, then migrate the working options back into infection.json.dist.