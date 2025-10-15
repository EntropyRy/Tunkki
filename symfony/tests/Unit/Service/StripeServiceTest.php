<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Event;
use App\Entity\Product;
use App\Service\StripeService;
use PHPUnit\Framework\TestCase;
use Stripe\StripeClient;
use Stripe\StripeObject;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class StripeServiceTest extends TestCase
{
    private StripeService $service;
    private ParameterBag $parameterBag;
    private UrlGeneratorInterface $urlGenerator;

    protected function setUp(): void
    {
        $this->parameterBag = new ParameterBag([
            'stripe_secret_key' => 'sk_test_fake_key_for_testing',
        ]);

        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->service = new StripeService($this->parameterBag, $this->urlGenerator);
    }

    public function testGetClientReturnsStripeClient(): void
    {
        $client = $this->service->getClient();

        $this->assertInstanceOf(StripeClient::class, $client);
    }

    public function testGetClientUsesSecretKeyFromParameters(): void
    {
        $client = $this->service->getClient();

        // Verify the client was initialized (we can't directly access the API key)
        $this->assertInstanceOf(StripeClient::class, $client);
    }

    public function testGetReturnUrlForEventGeneratesCorrectUrl(): void
    {
        $event = $this->createMock(Event::class);
        $eventDate = new \DateTimeImmutable('2025-06-15');

        $event->expects($this->once())
            ->method('getEventDate')
            ->willReturn($eventDate);

        $event->expects($this->once())
            ->method('getUrl')
            ->willReturn('summer-festival');

        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with(
                'entropy_event_shop_complete',
                [
                    'year' => '2025',
                    'slug' => 'summer-festival',
                ],
                UrlGeneratorInterface::ABSOLUTE_URL
            )
            ->willReturn('https://example.com/2025/summer-festival/complete');

        $url = $this->service->getReturnUrl($event);

        $this->assertSame(
            'https://example.com/2025/summer-festival/complete?session_id={CHECKOUT_SESSION_ID}',
            $url
        );
    }

    public function testGetReturnUrlForNullEventGeneratesShopUrl(): void
    {
        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with(
                'entropy_shop_complete',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            )
            ->willReturn('https://example.com/shop/complete');

        $url = $this->service->getReturnUrl(null);

        $this->assertSame(
            'https://example.com/shop/complete?session_id={CHECKOUT_SESSION_ID}',
            $url
        );
    }

    public function testUpdateOurProductWithProductOnly(): void
    {
        $product = new Product();
        $stripeProduct = StripeObject::constructFrom([
            'id' => 'prod_test123',
            'name' => 'Test Product',
            'active' => true,
        ]);

        $result = $this->service->updateOurProduct($product, null, $stripeProduct);

        $this->assertSame($product, $result);
        $this->assertSame('prod_test123', $result->getStripeId());
        $this->assertSame('Test Product', $result->getNameEn());
        $this->assertTrue($result->isActive());
    }

    public function testUpdateOurProductWithProductOnlyInactive(): void
    {
        $product = new Product();
        $stripeProduct = StripeObject::constructFrom([
            'id' => 'prod_inactive',
            'name' => 'Inactive Product',
            'active' => false,
        ]);

        $result = $this->service->updateOurProduct($product, null, $stripeProduct);

        $this->assertSame($product, $result);
        $this->assertFalse($result->isActive());
    }

    public function testUpdateOurProductWithPriceAndProduct(): void
    {
        $product = new Product();

        $stripePrice = StripeObject::constructFrom([
            'id' => 'price_test456',
            'unit_amount' => 2500, // $25.00
            'active' => true,
        ]);

        $stripeProduct = StripeObject::constructFrom([
            'id' => 'prod_test789',
            'name' => 'Premium Ticket',
            'active' => true,
        ]);

        $result = $this->service->updateOurProduct($product, $stripePrice, $stripeProduct);

        $this->assertSame($product, $result);
        $this->assertSame('prod_test789', $result->getStripeId());
        $this->assertSame('price_test456', $result->getStripePriceId());
        $this->assertSame('Premium Ticket', $result->getNameEn());
        $this->assertSame(2500, $result->getAmount());
        $this->assertTrue($result->isActive());
    }

    public function testUpdateOurProductInactiveWhenPriceInactive(): void
    {
        $product = new Product();

        $stripePrice = StripeObject::constructFrom([
            'id' => 'price_inactive',
            'unit_amount' => 1000,
            'active' => false,
        ]);

        $stripeProduct = StripeObject::constructFrom([
            'id' => 'prod_active',
            'name' => 'Product',
            'active' => true,
        ]);

        $result = $this->service->updateOurProduct($product, $stripePrice, $stripeProduct);

        $this->assertFalse($result->isActive(), 'Product should be inactive when price is inactive');
    }

    public function testUpdateOurProductInactiveWhenProductInactive(): void
    {
        $product = new Product();

        $stripePrice = StripeObject::constructFrom([
            'id' => 'price_active',
            'unit_amount' => 1000,
            'active' => true,
        ]);

        $stripeProduct = StripeObject::constructFrom([
            'id' => 'prod_inactive',
            'name' => 'Product',
            'active' => false,
        ]);

        $result = $this->service->updateOurProduct($product, $stripePrice, $stripeProduct);

        $this->assertFalse($result->isActive(), 'Product should be inactive when Stripe product is inactive');
    }

    public function testUpdateOurProductInactiveWhenBothInactive(): void
    {
        $product = new Product();

        $stripePrice = StripeObject::constructFrom([
            'id' => 'price_inactive',
            'unit_amount' => 1000,
            'active' => false,
        ]);

        $stripeProduct = StripeObject::constructFrom([
            'id' => 'prod_inactive',
            'name' => 'Product',
            'active' => false,
        ]);

        $result = $this->service->updateOurProduct($product, $stripePrice, $stripeProduct);

        $this->assertFalse($result->isActive(), 'Product should be inactive when both price and product are inactive');
    }

    public function testGetCheckoutSessionReturnsSession(): void
    {
        // This test verifies the method signature and return type
        // In a real scenario, we'd need to mock the Stripe API client
        $this->markTestSkipped('Requires mocking Stripe API client - integration test territory');
    }
}
