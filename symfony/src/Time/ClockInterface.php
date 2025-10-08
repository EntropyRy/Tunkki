<?php

declare(strict_types=1);

namespace App\Time;

/**
 * ClockInterface.
 *
 * Lightweight abstraction over retrieving "now" as an immutable timestamp.
 *
 * Motivation:
 *  - Eliminate scattered `new \DateTime()` / `new \DateTimeImmutable()` calls in entities & services.
 *  - Improve testability: time‑dependent logic (windows, expirations, presales) can be reliably frozen/advanced.
 *  - Prepare for future replacement with Symfony's Contracts ClockInterface (if/when fully adopted) or a more
 *    advanced time provider (e.g. leveraging monotonic + wall clock composition).
 *
 * Usage Guidance:
 *  - Inject this interface into domain/services requiring current time.
 *  - DO NOT construct new DateTimeImmutable manually in business logic once migration is complete.
 *  - Entities should (long term) avoid calling the clock directly; they should receive time
 *    values from services (facilitates pure unit tests / mutation testing).
 *
 * Extension Points:
 *  - A test implementation (e.g. FixedClock / FrozenClock) can implement this interface and be
 *    bound in the test container to yield deterministic timestamps.
 *  - A tracing or logging decorator could wrap an inner clock to capture temporal usage patterns.
 *
 * Contract:
 *  - now() MUST return a new immutable instance each call (unless a fixed test clock).
 *  - Timezone: by default relies on project PHP default timezone (can be refined if needed).
 */
interface ClockInterface
{
    /**
     * Return the current instant as a DateTimeImmutable.
     */
    public function now(): \DateTimeImmutable;
}

/*
 * AppClock.
 *
 * Default production clock implementation returning the real current time.
 * Free of additional logic to keep instantiation & inlining cheap.
 *
 * Replace or decorate (e.g. via Symfony service decoration) if you need logging, offsetting,
 * or controlled acceleration / slowdown in specialized environments.
 */

/*
 * Suggested (not yet created) test double example:
 *
 * final class FixedClock implements ClockInterface {
 *     public function __construct(private \DateTimeImmutable $fixed) {}
 *     public function now(): \DateTimeImmutable { return $this->fixed; }
 * }
 *
 * Or a mutable test clock:
 *
 * final class MutableTestClock implements ClockInterface {
 *     private \DateTimeImmutable $current;
 *     public function __construct(?\DateTimeImmutable $start = null) {
 *         $this->current = $start ?? new \DateTimeImmutable('2025-01-01T00:00:00Z');
 *     }
 *     public function now(): \DateTimeImmutable { return $this->current; }
 *     public function advance(string $modifier): void { $this->current = $this->current->modify($modifier); }
 * }
 *
 * Service Wiring (example in services.yaml):
 *
 *   App\Time\ClockInterface: '@App\Time\AppClock'
 *
 * Test Environment Override (services_test.yaml):
 *
 *   App\Time\ClockInterface: '@App\Time\FixedClock'
 *   App\Time\FixedClock:
 *       arguments:
 *           - '2025-01-01T12:00:00+00:00'
 *
 * Migration Plan (STAN-03):
 *  1. Introduce interface & default implementation (this file).
 *  2. Replace direct new \DateTime()/DateTimeImmutable() in services (NOT entities first).
 *  3. Introduce a ValueObject or service wrapper for complex temporal logic (e.g. PresaleWindow).
 *  4. Gradually refactor entity temporal logic into services using ClockInterface.
 *  5. Add mutation tests ensuring temporal branching guarded by controlled clock advances.
 */
