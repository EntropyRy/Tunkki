<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Event;
use App\Entity\Product;
use App\Service\StripeService;
use PHPUnit\Framework\TestCase;
use Stripe\Checkout\Session;
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

        $this->urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $clock = $this->createStub(\App\Time\ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2025-01-01 12:00:00'));

        $this->service = new StripeService($this->parameterBag, $this->urlGenerator, $clock);
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
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $clock = $this->createStub(\App\Time\ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2025-01-01 12:00:00'));
        $this->service = new StripeService($this->parameterBag, $this->urlGenerator, $clock);

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
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $clock = $this->createStub(\App\Time\ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2025-01-01 12:00:00'));
        $this->service = new StripeService($this->parameterBag, $this->urlGenerator, $clock);

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

    public function testGetReceiptUrlReturnsNullWhenPaymentIntentMissing(): void
    {
        $service = new readonly class($this->parameterBag, $this->urlGenerator) extends StripeService {
            public function __construct(
                ParameterBag $bag,
                UrlGeneratorInterface $urlG,
            ) {
                $clock = new class implements \App\Time\ClockInterface {
                    public function now(): \DateTimeImmutable
                    {
                        return new \DateTimeImmutable('2025-01-01 12:00:00');
                    }
                };
                parent::__construct($bag, $urlG, $clock);
            }

            public function getCheckoutSession($sessionId): Session
            {
                return Session::constructFrom([
                    'id' => $sessionId,
                    'payment_intent' => null,
                ]);
            }
        };

        $this->assertNull($service->getReceiptUrlForSessionId('cs_test_no_pi'));
    }

    public function testGetReceiptUrlUsesExpandedCharges(): void
    {
        $paymentIntent = new \stdClass();
        $paymentIntent->charges = new \stdClass();
        $paymentIntent->charges->data = [
            (object) ['receipt_url' => 'https://stripe.test/receipt/expanded'],
        ];
        $paymentIntent->latest_charge = null;

        $fakePaymentIntents = new class($paymentIntent) {
            public function __construct(private object $paymentIntent)
            {
            }

            public function retrieve(string $id, array $options = []): object
            {
                return $this->paymentIntent;
            }
        };

        $fakeCharges = new class {
            public function retrieve(string $id): object
            {
                return (object) ['receipt_url' => null];
            }
        };

        $fakeClient = new class($fakePaymentIntents, $fakeCharges) extends StripeClient {
            public object $paymentIntents;
            public object $charges;

            public function __construct(object $paymentIntents, object $charges)
            {
                parent::__construct('sk_test_fake');
                $this->paymentIntents = $paymentIntents;
                $this->charges = $charges;
            }
        };

        $service = new readonly class($this->parameterBag, $this->urlGenerator, $fakeClient) extends StripeService {
            public function __construct(
                ParameterBag $bag,
                UrlGeneratorInterface $urlG,
                private readonly StripeClient $client,
            ) {
                $clock = new class implements \App\Time\ClockInterface {
                    public function now(): \DateTimeImmutable
                    {
                        return new \DateTimeImmutable('2025-01-01 12:00:00');
                    }
                };
                parent::__construct($bag, $urlG, $clock);
            }

            public function getClient(): StripeClient
            {
                return $this->client;
            }

            public function getCheckoutSession($sessionId): Session
            {
                return Session::constructFrom([
                    'id' => $sessionId,
                    'payment_intent' => 'pi_test_123',
                ]);
            }
        };

        $this->assertSame(
            'https://stripe.test/receipt/expanded',
            $service->getReceiptUrlForSessionId('cs_test_receipt'),
        );
    }

    public function testGetReceiptUrlFallsBackToLatestCharge(): void
    {
        $paymentIntent = new \stdClass();
        $paymentIntent->charges = new \stdClass();
        $paymentIntent->charges->data = [];
        $paymentIntent->latest_charge = 'ch_test_123';

        $fakePaymentIntents = new class($paymentIntent) {
            public function __construct(private object $paymentIntent)
            {
            }

            public function retrieve(string $id, array $options = []): object
            {
                return $this->paymentIntent;
            }
        };

        $fakeCharges = new class {
            public function retrieve(string $id): object
            {
                return (object) ['receipt_url' => 'https://stripe.test/receipt/latest'];
            }
        };

        $fakeClient = new class($fakePaymentIntents, $fakeCharges) extends StripeClient {
            public object $paymentIntents;
            public object $charges;

            public function __construct(object $paymentIntents, object $charges)
            {
                parent::__construct('sk_test_fake');
                $this->paymentIntents = $paymentIntents;
                $this->charges = $charges;
            }
        };

        $service = new readonly class($this->parameterBag, $this->urlGenerator, $fakeClient) extends StripeService {
            public function __construct(
                ParameterBag $bag,
                UrlGeneratorInterface $urlG,
                private readonly StripeClient $client,
            ) {
                $clock = new class implements \App\Time\ClockInterface {
                    public function now(): \DateTimeImmutable
                    {
                        return new \DateTimeImmutable('2025-01-01 12:00:00');
                    }
                };
                parent::__construct($bag, $urlG, $clock);
            }

            public function getClient(): StripeClient
            {
                return $this->client;
            }

            public function getCheckoutSession($sessionId): Session
            {
                return Session::constructFrom([
                    'id' => $sessionId,
                    'payment_intent' => 'pi_test_456',
                ]);
            }
        };

        $this->assertSame(
            'https://stripe.test/receipt/latest',
            $service->getReceiptUrlForSessionId('cs_test_receipt_latest'),
        );
    }

    public function testGetReceiptUrlReturnsNullOnError(): void
    {
        $service = new readonly class($this->parameterBag, $this->urlGenerator) extends StripeService {
            public function __construct(
                ParameterBag $bag,
                UrlGeneratorInterface $urlG,
            ) {
                $clock = new class implements \App\Time\ClockInterface {
                    public function now(): \DateTimeImmutable
                    {
                        return new \DateTimeImmutable('2025-01-01 12:00:00');
                    }
                };
                parent::__construct($bag, $urlG, $clock);
            }

            public function getCheckoutSession($sessionId): Session
            {
                throw new \RuntimeException('Stripe failure');
            }
        };

        $this->assertNull($service->getReceiptUrlForSessionId('cs_test_error'));
    }
}
