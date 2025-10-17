# PHPStan Analysis Triage Report

**Generated**: 2025-10-17
**Commit**: Post-generics annotation completion (Task 31D)
**Analysis Level**: 5 (default) and max (strict)

---

## Summary

| Level | Errors | Status |
|-------|--------|--------|
| **Level 5** | **0** | ✅ **Clean** |
| **Level max** | **972** | ⚠️ Needs triage |

**Achievement**: Level 5 is now **error-free** after generics annotations and nullability batch 1.

---

## Level Max Error Breakdown (972 total)

Categorized by error identifier (top issues first):

| Category | Count | Priority | Notes |
|----------|-------|----------|-------|
| `method.nonObject` | 287 | **High** | Calling methods on mixed/unknown types |
| `argument.type` | 160 | **High** | Type mismatches in function arguments |
| `missingType.parameter` | 90 | **Medium** | Missing parameter type hints |
| `missingType.iterableValue` | 76 | **Medium** | Arrays without `@param array<Type>` |
| `missingType.generics` | 59 | **Medium** | Missing generic annotations (non-Sonata) |
| `return.type` | 58 | **High** | Return type mismatches |
| `offsetAccess.nonOffsetAccessible` | 50 | **Medium** | Array access on non-array types |
| `cast.string` | 40 | **Low** | Mixed to string casts |
| `binaryOp.invalid` | 33 | **Medium** | Invalid binary operations (e.g., mixed + int) |
| `assign.propertyType` | 27 | **High** | Property assignment type mismatches |
| `foreach.nonIterable` | 23 | **Medium** | Foreach on non-iterable types |
| `offsetAccess.invalidOffset` | 21 | **Medium** | Invalid array key types |
| `method.notFound` | 13 | **High** | Calling non-existent methods |
| `missingType.property` | 9 | **Medium** | Missing property type hints |
| `cast.int` | 8 | **Low** | Mixed to int casts |
| `offsetAccess.notFound` | 7 | **Medium** | Undefined array keys |
| `missingType.return` | 3 | **Medium** | Missing return type hints |
| Other (assignOp, clone, property) | 6 | **Low** | Miscellaneous edge cases |

---

## High-Priority Remediation Targets

### 1. method.nonObject (287 errors)
**Issue**: Calling methods on `mixed` or potentially null objects without type narrowing.

**Example Pattern**:
```php
// Before
$result = $service->getSomething(); // returns mixed
$result->doSomething(); // PHPStan error: method.nonObject

// After
$result = $service->getSomething();
assert($result instanceof ExpectedType);
$result->doSomething(); // OK
```

**Action Plan**:
- Add return type hints to services/repositories
- Use assertions or instanceof checks before method calls
- Add `@var` PHPDoc where type hints aren't feasible

**Target**: Reduce by 50% in next batch (MT19 continuation)

---

### 2. argument.type (160 errors)
**Issue**: Passing wrong type to function/method parameters.

**Common Causes**:
- Missing type narrowing before passing variables
- `mixed` from array access without validation
- Nullable types passed where non-null expected

**Example Pattern**:
```php
// Before
function processName(string $name): void { ... }
$data = $_POST['name']; // mixed
processName($data); // PHPStan error: argument.type

// After
function processName(string $name): void { ... }
$data = $_POST['name'] ?? '';
assert(is_string($data));
processName($data); // OK
```

**Action Plan**:
- Add type assertions in controllers before service calls
- Use Symfony validation constraints earlier
- Add strict typehints to all new methods

**Target**: Reduce by 40% in next batch

---

### 3. return.type (58 errors)
**Issue**: Method declares return type but returns incompatible value.

**Common Causes**:
- Returning null when non-nullable declared
- Returning mixed from database/array access
- Incorrect generic type specifications

**Example Pattern**:
```php
// Before
public function findUser(): User
{
    return $this->userRepo->find($id); // returns User|null
    // PHPStan error: return.type
}

// After
public function findUser(): ?User
{
    return $this->userRepo->find($id); // OK
}
```

**Action Plan**:
- Audit repository methods for nullable returns
- Update Admin class lifecycle methods (prePersist, etc.)
- Add strict assertions where null is truly unexpected

**Target**: Fix all 58 in focused pass (MT41 - new task)

---

### 4. assign.propertyType (27 errors)
**Issue**: Assigning wrong type to entity/class properties.

**Common Causes**:
- Assigning `mixed` to typed properties
- DateTimeImmutable/DateTime mismatches
- Collection generic mismatches

**Example Pattern**:
```php
// Before
#[ORM\Column]
private \DateTimeImmutable $createdAt;

$entity->createdAt = new \DateTime(); // PHPStan error: assign.propertyType

// After
$entity->createdAt = new \DateTimeImmutable(); // OK
```

**Action Plan**:
- Continue MT37 nullability batch 2 (Event/Happening temporal fields)
- Audit all DateTime assignments (MT22/MT39 tasks)
- Review collection property assignments

**Target**: Fix all 27 during MT37 batch 2 completion

---

### 5. method.notFound (13 errors)
**Issue**: Calling methods that don't exist on the declared type.

**Risk**: **High** - These are potential runtime errors!

**Action Plan**:
- **Immediate review required**
- Check each occurrence manually
- Either:
  - Fix typo/method name
  - Add missing method
  - Add proper type hint/cast

**Target**: Fix all 13 immediately (MT42 - new task, HIGH PRIORITY)

---

## Medium-Priority Items

### missingType.parameter (90 errors)
- Add `@param` annotations where type hints not feasible
- Convert to strict type hints where possible
- Document complex array structures with `@param array<string, mixed>`

### missingType.iterableValue (76 errors)
- Add array shape annotations: `@return array{id: int, name: string}`
- Use generics for collections: `@return array<Event>`
- Consider ValueObject wrappers for complex structures

### missingType.generics (59 errors)
- Non-Sonata generics (Collections, Forms, etc.)
- Add `@var Collection<int, Product>` to entity properties
- Add `@extends` for non-Admin parent classes

---

## Low-Priority Items

### cast.string (40 errors) & cast.int (8 errors)
- Current pattern: `(string)$mixed` where mixed comes from array access
- Acceptable short-term; consider adding validation layer
- Defer until high-priority items addressed

### binaryOp.invalid (33 errors)
- Mixed math operations (e.g., `$mixed + 5`)
- Add type assertions before calculations
- Low runtime risk if validated at boundaries

---

## Exclusions & Baseline Policy

**Current Policy**: No baseline file. Fix issues incrementally.

**Proposed Exceptions**:
1. **Sonata Admin legacy code** (until upstream types improve)
2. **Third-party bundle integrations** (document with rationale)
3. **Temporary mixed from complex Twig/Symfony internals**

**Each exception MUST**:
- Have documented rationale in `phpstan.neon`
- Include expiry review date
- Link to upstream issue (if applicable)

---

## Next Steps (Post-Triage Action Plan)

### Immediate (Week of 2025-10-17)
1. **MT42**: Fix all 13 `method.notFound` errors (HIGH RISK) ✅ Create task
2. **MT37 Batch 2**: Complete Event/Happening nullability → fixes ~27 `assign.propertyType`
3. Run level 5 continuously in CI (already green)

### Short-Term (Next 2 weeks)
4. **MT41**: Fix all 58 `return.type` mismatches (focused pass)
5. **MT43**: Reduce `method.nonObject` by 50% (add assertions/type hints) ✅ Create task
6. **MT44**: Reduce `argument.type` by 40% (controller/service boundaries) ✅ Create task

### Medium-Term (End of Month)
7. Add remaining `@param` annotations (90 missing)
8. Add array shape documentation (76 iterableValue)
9. Complete non-Sonata generics (59 remaining)

### Long-Term (Optional)
10. Evaluate level 6-8 progression
11. Consider enforcing max level for new code only (diff-based)
12. Integrate with mutation testing survivors (cross-reference with Entity boundary tests)

---

## Historical Comparison

| Milestone | Date | Level 5 Errors | Level Max Errors | Notes |
|-----------|------|----------------|------------------|-------|
| Pre-generics | 2025-10-02 | ~450 | ~1200 | Mostly `missingType.generics` |
| Post-generics (31D) | 2025-10-08 | ~120 | ~1100 | All Sonata Admin annotated |
| Nullability Batch 1 (MT37) | 2025-10-09 | ~40 | ~1050 | Contract, Email, User, Member |
| **Current (Post 31E)** | **2025-10-17** | **0** ✅ | **972** | Level 5 clean! |

**Progress**:
- Level 5: 100% clean (from 450 → 0) 🎉
- Level max: 19% reduction (1200 → 972)

---

## Success Criteria (Level Max)

**Phase 1** (Target: 2025-11-01):
- [ ] Level 5: 0 errors (✅ **DONE**)
- [ ] Level max: <600 errors (reduce by 38%)
- [ ] All `method.notFound` fixed (0 errors)
- [ ] All `return.type` fixed (0 errors)

**Phase 2** (Target: 2025-12-01):
- [ ] Level max: <300 errors (reduce by 69% from current)
- [ ] All high-priority categories <20 errors each
- [ ] Document remaining exceptions in baseline

**Phase 3** (Optional):
- [ ] Level max: <100 errors
- [ ] Evaluate enforcement in CI

---

## Notes

- **Sonata Admin** types are still improving upstream; some mixed returns unavoidable
- **Twig Extensions** (`LocalizedUrlExtension`) account for ~40 errors alone (mostly cast.string, method.nonObject from Twig mixed returns)
- **Doctrine Proxies** sometimes cause false positives; use `@var` assertions when safe
- **Form/Validator** components have many mixed returns; validate at boundaries instead

---

**Last Updated**: 2025-10-17
**Next Review**: After MT37 Batch 2 completion (expected 2025-10-24)
