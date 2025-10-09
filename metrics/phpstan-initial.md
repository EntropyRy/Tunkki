# PHPStan Initial Metrics Snapshot

Date: 2025-10-08
Commit: (fill with short SHA at capture)
Source: metrics/phpstan-report.json (generated via project’s static-analysis target inside the Docker FPM container)

This snapshot records the current PHPStan findings to guide structured cleanup and to establish a baseline for measuring progress over time. Treat this file as append-only; create new dated snapshots for later runs.

--------------------------------------------------------------------------------
## 1) Totals

- Total issues (messages): 476
- Global errors: 0 (tool-level)
- Report path: metrics/phpstan-report.json

Context (from project policy):
- Preferred level for triage: level=5 (raise to max after generics/nullability cleanup).
- All analysis must run inside the FPM container or via provided Make targets.

--------------------------------------------------------------------------------
## 2) Category Breakdown (by identifier)

Count — Identifier — Description/next-step

- 96 — doctrine.columnType — Property PHP type vs ORM column mismatch (incl. nullability, DateTimeImmutable vs DateTimeInterface).
  - Action: Align PHP property types and ORM mapping. Prefer DateTimeImmutable consistently. Fix nullable vs non-null discrepancies.

- 95 — function.alreadyNarrowedType — Redundant method_exists()/instanceof checks on known types.
  - Action: Remove defensive checks against methods that always exist for the concrete class.

- 88 — missingType.iterableValue — @method docblocks missing value types for array parameters (findBy/findOneBy).
  - Action: Add generics or concrete array value types in PHPDoc for repository method tags.

- 36 — class.notFound — Unknown symbols in PHPStan analysis (e.g., Zenstruck Foundry RepositoryProxy).
  - Action: Ensure dev dependencies are autoloaded for analysis, add proper phpstan includes/stubs, or replace incorrect annotations with native types.

- 35 — missingType.generics — Missing generic type parameters (e.g., ServiceEntityRepository<TEntity>, Sonata/Gedmo/NestedTree).
  - Action: Add @extends annotations specifying concrete entity types on repositories/admins; see CLAUDE.md §13.

- 33 — doctrine.findOneByArgument — Using non-existent fields in findOneBy/findBy criteria for Sonata entities.
  - Action: Replace invalid criteria with valid field names or use QueryBuilder. Verify Sonata entities’ field map.

- 27 — doctrine.associationType — Collection/association PHP types not constrained to the correct entity type.
  - Action: Add Collection<SpecificEntity> generics and correct property PHPDoc/types.

- 23 — offsetAssign.valueType — Assigning wrong element types into typed ArrayCollections.
  - Action: Tighten collection generics and fix add/remove methods to enforce correct value classes.

- 13 — varTag.nativeType — @var PHPDoc conflicts with native type.
  - Action: Make PHPDoc match PHP native type, or adjust the property type if PHPDoc is the intended truth.

- 11 — argument.type — Method parameter type mismatches (e.g., passing Security UserInterface where App\Entity\User is required).
  - Action: Normalize to domain User type or widen signatures carefully where intentional.

- 5 — assign.propertyType — Assigning incompatible types to properties (e.g., DateTimeInterface into DateTimeImmutable|null).
  - Action: Convert to DateTimeImmutable or relax property type only if domain permits.

- 4 — method.notFound — Calls to methods that don’t exist for the static type (e.g., DateTimeInterface::modify()).
  - Action: Ensure variables are DateTimeImmutable before calling modify(), or cast/clone properly.

- 4 — if.alwaysTrue — Dead conditions.
  - Action: Remove or rewrite to meaningful guard conditions.

- 1 — return.unusedType; 1 — return.type; 1 — phpDoc.parseError; 1 — notIdentical.alwaysTrue; 1 — instanceof.alwaysTrue; 1 — ignore.unmatchedLine
  - Action: Fix signatures, malformed PHPDoc, and stray ignore lines.

--------------------------------------------------------------------------------
## 3) Hotspots (by namespace hints)

- Entities: High concentration of doctrine.columnType and association/generic issues (nullability, DateTimeImmutable, typed collections).
- Repositories: missingType.iterableValue on @method tags; missing generics on ServiceEntityRepository subclasses.
- Sonata fixtures/stories: doctrine.findOneByArgument (invalid criteria fields), class.notFound for Foundry proxies.
- Factories: method.notFound due to DateTimeInterface::modify() calls when the variable’s static type isn’t immutable; tighten types or coerce.

--------------------------------------------------------------------------------
## 4) Remediation Plan (guided by CLAUDE.md priorities)

1) Generics & Admin/Repository annotations
   - Add @extends …<Entity> for all Sonata Admins and CRUD controllers.
   - Add @extends ServiceEntityRepository<Entity> on repositories; fix @method tags with value types.

2) Nullability and temporal consistency
   - Convert temporal properties to DateTimeImmutable where intended non-mutable usage.
   - Align ORM nullable flags with PHP nullable types (and vice versa).
   - Update factories and constructor defaults accordingly.

3) Doctrine criteria correctness
   - Audit findOneBy/findBy against Sonata entities; replace invalid fields with correct columns or use QueryBuilder.

4) Remove redundant guard checks
   - Drop method_exists checks that always evaluate true on concrete types.

5) Collection typing
   - Add Collection<ConcreteEntity> PHPDoc and enforce correct element types in add/remove methods.

6) Parameter/return types
   - Normalize mismatches (UserInterface vs App\Entity\User) at boundaries; add explicit adapters where necessary.

Record deltas after each step (see §6) and commit snapshots.

--------------------------------------------------------------------------------
## 5) How to Reproduce

- Fast subset:
  - make stan-fast
- Full analysis (default level=5):
  - make stan
- JSON report (overwrites metrics/phpstan-report.json):
  - make stan-json

Policy:
- Always run inside the Docker FPM container via the make targets (see CLAUDE.md §2–3).

--------------------------------------------------------------------------------
## 6) Delta Logging Template (Append on next run)

Add a new dated section below when you re-run analysis.

YYYY-MM-DD (Run context: level=5 unless stated)
- Total issues: <n> (prev: 476)  Δ: <n>
- Top categories deltas:
  - doctrine.columnType: <n> (prev: 96)
  - function.alreadyNarrowedType: <n> (prev: 95)
  - missingType.iterableValue: <n> (prev: 88)
  - class.notFound: <n> (prev: 36)
  - missingType.generics: <n> (prev: 35)
  - doctrine.findOneByArgument: <n> (prev: 33)
  - doctrine.associationType: <n> (prev: 27)
  - offsetAssign.valueType: <n> (prev: 23)
  - varTag.nativeType: <n> (prev: 13)
  - argument.type: <n> (prev: 11)
  - assign.propertyType: <n> (prev: 5)
  - method.notFound: <n> (prev: 4)
  - if.alwaysTrue: <n> (prev: 4)
  - [others if changed]
- Notable fixes:
  - [bullet per fix group with PR/commit refs]
- Next focus:
  - [1–3 items]

--------------------------------------------------------------------------------
## 7) Notes and Constraints

- Do not introduce broad ignoreErrors blocks; each ignore must include rationale and expiry (see CLAUDE.md §5).
- Prefer structural fixes (types/mappings) to docblock-only patches unless you’re bridging a third-party limitation.
- After generics and nullability cleanup, consider raising the PHPStan level and re-baselining.

(End of initial PHPStan metrics snapshot)