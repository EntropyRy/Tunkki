<?php

declare(strict_types=1);

namespace App\Tests\Unit\Form;

use App\Entity\Happening;
use App\Form\HappeningType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Unit tests for HappeningType form event listeners.
 *
 * These tests cover defensive guard clauses that cannot be reached
 * through functional tests because Symfony's form component guarantees
 * the data types in normal operation.
 */
final class HappeningTypeTest extends TestCase
{
    private HappeningType $formType;

    protected function setUp(): void
    {
        $this->formType = new HappeningType(new AsciiSlugger());
    }

    /**
     * Test PRE_SUBMIT listener returns early when data is not an array.
     * Covers line 159.
     */
    public function testPreSubmitReturnsEarlyWhenDataIsNotArray(): void
    {
        $builder = $this->createStub(FormBuilderInterface::class);
        $listeners = [];

        $builder->method('add')->willReturnSelf();
        $builder->method('addEventListener')
            ->willReturnCallback(static function (string $eventName, callable $listener) use (&$listeners, $builder) {
                $listeners[$eventName][] = $listener;

                return $builder;
            });

        $this->formType->buildForm($builder, []);

        // Get PRE_SUBMIT listeners
        $preSubmitListeners = $listeners[FormEvents::PRE_SUBMIT] ?? [];
        $this->assertNotEmpty($preSubmitListeners, 'PRE_SUBMIT listener should be registered');

        // Create event with non-array data (null)
        $form = $this->createStub(FormInterface::class);
        $event = new FormEvent($form, null);

        // Call the listener - should return early without exception
        $preSubmitListeners[0]($event);

        // If we get here without exception, the guard clause worked
        $this->assertNull($event->getData());
    }

    /**
     * Test SUBMIT listener returns early when data is not a Happening.
     * Covers line 187.
     */
    public function testSubmitReturnsEarlyWhenDataIsNotHappening(): void
    {
        $builder = $this->createStub(FormBuilderInterface::class);
        $listeners = [];

        $builder->method('add')->willReturnSelf();
        $builder->method('addEventListener')
            ->willReturnCallback(static function (string $eventName, callable $listener) use (&$listeners, $builder) {
                $listeners[$eventName][] = $listener;

                return $builder;
            });

        $this->formType->buildForm($builder, []);

        // Get SUBMIT listeners
        $submitListeners = $listeners[FormEvents::SUBMIT] ?? [];
        $this->assertNotEmpty($submitListeners, 'SUBMIT listener should be registered');

        // Create event with non-Happening data (stdClass)
        $form = $this->createStub(FormInterface::class);
        $event = new FormEvent($form, new \stdClass());

        // Call the listener - should return early without exception
        $submitListeners[0]($event);

        // If we get here without exception, the guard clause worked
        $this->assertInstanceOf(\stdClass::class, $event->getData());
    }

    /**
     * Test POST_SUBMIT listener returns early when data is not a Happening.
     * Covers line 208.
     */
    public function testPostSubmitReturnsEarlyWhenDataIsNotHappening(): void
    {
        $builder = $this->createStub(FormBuilderInterface::class);
        $listeners = [];

        $builder->method('add')->willReturnSelf();
        $builder->method('addEventListener')
            ->willReturnCallback(static function (string $eventName, callable $listener) use (&$listeners, $builder) {
                $listeners[$eventName][] = $listener;

                return $builder;
            });

        $this->formType->buildForm($builder, []);

        // Get POST_SUBMIT listeners
        $postSubmitListeners = $listeners[FormEvents::POST_SUBMIT] ?? [];
        $this->assertNotEmpty($postSubmitListeners, 'POST_SUBMIT listener should be registered');

        // Create event with non-Happening data (null)
        $form = $this->createStub(FormInterface::class);
        $form->method('getData')->willReturn(null);

        $event = new FormEvent($form, null);

        // Call the listener - should return early without exception
        $postSubmitListeners[0]($event);

        // If we get here without exception, the guard clause worked
        $this->assertTrue(true);
    }
}
