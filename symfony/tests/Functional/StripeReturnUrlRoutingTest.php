<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\EventFactory;
use App\Service\StripeService;
use App\Tests\_Base\FixturesWebTestCase;

/**
 * StripeReturnUrlRoutingTest.
 *
 * Verifies that StripeService generates correct return URLs for both:
 * - General store checkout (event = null)
 * - Event-specific checkout (event != null)
 *
 * Return URLs must include the {CHECKOUT_SESSION_ID} placeholder which
 * Stripe replaces with the actual session ID when redirecting customers
 * after payment completion.
 *
 * Roadmap alignment:
 * - CLAUDE.md §4: Factory-driven, structural assertions
 * - STRIPE_MOCKING_PLAN.md: Return URL routing tests
 */
final class StripeReturnUrlRoutingTest extends FixturesWebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
    }

    public function testGeneralShopReturnUrlRoutesCorrectly(): void
    {
        $service = static::getContainer()->get(StripeService::class);
        $url = $service->getReturnUrl(null);

        $this->assertMatchesRegularExpression('#/kauppa/valmis#', $url);
        $this->assertMatchesRegularExpression('/session_id=\\{CHECKOUT_SESSION_ID\\}/', $url);
    }

    public function testEventShopReturnUrlRoutesCorrectly(): void
    {
        $event = EventFactory::new()->create([
            'url' => 'test-event',
            'eventDate' => new \DateTimeImmutable('2025-06-15'),
        ]);

        $service = static::getContainer()->get(StripeService::class);
        $url = $service->getReturnUrl($event);

        $this->assertMatchesRegularExpression('#/2025/test-event/valmis#', $url);
        $this->assertMatchesRegularExpression('/session_id=\\{CHECKOUT_SESSION_ID\\}/', $url);
    }
}
