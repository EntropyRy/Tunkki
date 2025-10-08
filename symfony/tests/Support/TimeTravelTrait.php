<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Time\AppClock;
use App\Time\ClockInterface;
use App\Time\FixedClock;
use App\Time\MutableClock;

/**
 * TimeTravelTrait.
 *
 * Provides high-level helper methods for manipulating the test clock in
 * functional, integration, or (container-aware) unit tests. This trait
 * assumes the project service container binds App\Time\ClockInterface
 * in the test environment (when@test) to a controllable implementation.
 *
 * It transparently upgrades any non‑mutable clock (FixedClock / AppClock)
 * to a MutableClock so subsequent travel/advance operations are always
 * supported without additional test boilerplate.
 *
 * Typical Usage:
 *
 *   use TimeTravelTrait;
 *
 *   public function testPublicationWindow(): void
 *   {
 *       $event = EventFactory::new()->draft()->create();
 *       $this->freeze('2025-01-01T12:00:00+00:00');
 *       self::assertFalse($this->decider->isPublished($event));
 *
 *       $this->travel('+2 hours'); // advance simulated time
 *       self::assertTrue($this->decider->isPublished($eventScheduledEarlier));
 *   }
 *
 * Methods:
 *   now()              -> DateTimeImmutable current simulated time
 *   freeze($iso)       -> Set clock to exact ISO8601 / parseable string
 *   travel($modifier)  -> Relative modify (e.g. '+15 minutes', '-1 day')
 *   travelSeconds(n)   -> Convenience wrapper
 *   travelMinutes(n)
 *   travelHours(n)
 *   travelDays(n)
 *   travelTo($iso)     -> Alias of freeze()
 *
 * Design Notes:
 * - All helpers return $this for fluent usage unless returning a value.
 * - Fails fast with informative exceptions if the container is unreachable.
 * - Ensures a single MutableClock instance is shared after first mutation.
 *
 * Limitations:
 * - Not thread safe: each parallel test process must have its own container.
 * - Do not rely on microsecond precision boundaries for business logic tests
 *   unless explicitly required; prefer second-level granularity for clarity.
 *
 * @internal test helper only; do NOT use inside production code
 */
trait TimeTravelTrait
{
    /**
     * Get the current simulated time (immutable).
     */
    protected function now(): \DateTimeImmutable
    {
        return $this->getClock()->now();
    }

    /**
     * Set the clock to an exact instant (ISO8601 or any string accepted by DateTimeImmutable).
     *
     * @return $this
     */
    protected function freeze(string|\DateTimeImmutable $instant): static
    {
        $clock = $this->obtainMutableClock();
        $dt = $instant instanceof \DateTimeImmutable ? $instant : new \DateTimeImmutable($instant);
        $clock->setNow($dt);

        return $this;
    }

    /**
     * Alias of freeze() for readability in some tests.
     *
     * @return $this
     */
    protected function travelTo(string|\DateTimeImmutable $instant): static
    {
        return $this->freeze($instant);
    }

    /**
     * Advance (or rewind) the simulated time using a DateTimeImmutable::modify() expression.
     *
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    protected function travel(string $relativeModifier): static
    {
        $this->obtainMutableClock()->advance($relativeModifier);

        return $this;
    }

    /**
     * Advance by a number of seconds (negative rewinds).
     */
    protected function travelSeconds(int $seconds): static
    {
        $this->obtainMutableClock()->advanceSeconds($seconds);

        return $this;
    }

    /**
     * Advance by a number of minutes (negative rewinds).
     */
    protected function travelMinutes(int $minutes): static
    {
        $this->obtainMutableClock()->advanceMinutes($minutes);

        return $this;
    }

    /**
     * Advance by a number of hours (negative rewinds).
     */
    protected function travelHours(int $hours): static
    {
        $this->obtainMutableClock()->advanceHours($hours);

        return $this;
    }

    /**
     * Advance by a number of days (negative rewinds).
     */
    protected function travelDays(int $days): static
    {
        $this->obtainMutableClock()->advanceDays($days);

        return $this;
    }

    /**
     * Ensure we have a MutableClock; upgrade container binding transparently if needed.
     *
     * @throws \RuntimeException when the test container cannot be accessed
     */
    private function obtainMutableClock(): MutableClock
    {
        $clock = $this->getClock();

        if ($clock instanceof MutableClock) {
            return $clock;
        }

        // Upgrade strategy: wrap current "now" instant into a new MutableClock and rebind.
        $mutable = new MutableClock($clock->now());

        $this->setClockService($mutable);

        return $mutable;
    }

    /**
     * Retrieve the current ClockInterface from the test container.
     *
     * @throws \RuntimeException if container or service unavailable
     */
    private function getClock(): ClockInterface
    {
        if (!method_exists($this, 'getContainer')) {
            throw new \RuntimeException('TimeTravelTrait requires the test case to provide getContainer() (e.g., extend KernelTestCase/WebTestCase).');
        }

        $container = self::getContainer();
        if (!$container->has(ClockInterface::class)) {
            throw new \RuntimeException('ClockInterface service not found. Ensure services.yaml binds App\Time\ClockInterface and when@test override exists.');
        }

        /** @var ClockInterface $clock */
        $clock = $container->get(ClockInterface::class);

        return $clock;
    }

    /**
     * Replace the ClockInterface service in the container with a new implementation.
     *
     * @psalm-suppress MixedAssignment (container returns mixed)
     */
    private function setClockService(ClockInterface $newClock): void
    {
        $container = self::getContainer();

        // Some Symfony versions expose set() only in test container; assume available in test env.
        if (!method_exists($container, 'set')) {
            throw new \RuntimeException('Container does not support set(). Cannot replace ClockInterface at runtime.');
        }

        $container->set(ClockInterface::class, $newClock);

        // Optional: also expose a named service for direct retrieval consistency (if present previously).
        if (method_exists($container, 'has') && $container->has('test.fixed_clock')) {
            $container->set('test.fixed_clock', $newClock->now());
        }
    }

    /**
     * Convenience assertion helper (optional usage).
     */
    protected function assertNowEquals(string|\DateTimeImmutable $expected, string $message = ''): void
    {
        $exp = $expected instanceof \DateTimeImmutable ? $expected : new \DateTimeImmutable($expected);
        $actual = $this->now();

        // Use PHPUnit's assertion if available; fall back to generic comparison otherwise.
        if (method_exists($this, 'assertSame')) {
            /* @psalm-suppress UndefinedMethod */
            $this->assertSame(
                $exp->format(\DateTimeInterface::ATOM),
                $actual->format(\DateTimeInterface::ATOM),
                '' !== $message ? $message : 'Simulated "now" does not match expected instant.'
            );
        } else {
            if ($exp->getTimestamp() !== $actual->getTimestamp()) {
                throw new \RuntimeException('' !== $message ? $message : sprintf('Time mismatch: expected %s got %s', $exp->format(\DateTimeInterface::ATOM), $actual->format(\DateTimeInterface::ATOM)));
            }
        }
    }
}
