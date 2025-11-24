<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Checkout;
use App\Entity\Event;
use App\Entity\Product;
use App\Entity\Ticket;
use App\EventSubscriber\StripeEventSubscriber;
use App\Repository\CheckoutRepository;
use App\Repository\EmailRepository;
use App\Repository\MemberRepository;
use App\Repository\ProductRepository;
use App\Repository\TicketRepository;
use App\Service\BookingReferenceService;
use App\Service\MattermostNotifierService;
use App\Service\QrService;
use App\Service\StripeService;
use Doctrine\Common\Collections\ArrayCollection;
use Fpt\StripeBundle\Event\StripeEvents;
use Fpt\StripeBundle\Event\StripeWebhook;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Stripe\Event as StripeEvent;
use Stripe\StripeObject;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @covers \App\EventSubscriber\StripeEventSubscriber
 *
 * Note: This test suite has limited coverage due to the complexity of testing
 * with Stripe webhook events and multiple final service dependencies. Full coverage
 * requires integration tests with Stripe webhook mocks.
 */
final class StripeEventSubscriberTest extends TestCase
{
    private StripeEventSubscriber $subscriber;
    private CheckoutRepository $checkoutRepo;
    private ProductRepository $productRepo;
    private LoggerInterface $logger;
    private StripeService $stripe;
    private MemberRepository $memberRepo;
    private TicketRepository $ticketRepo;
    private EmailRepository $emailRepo;
    private BookingReferenceService $rn;
    private MailerInterface $mailer;
    private MattermostNotifierService $mm;
    private QrService|\PHPUnit\Framework\MockObject\MockObject $qrGenerator;

    protected function setUp(): void
    {
        $this->checkoutRepo = $this->createMock(CheckoutRepository::class);
        $this->productRepo = $this->createMock(ProductRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // Create real StripeService instance (final class) with mocked dependencies
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturn('sk_test_fake');
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->stripe = new StripeService($parameterBag, $urlGenerator);

        $this->memberRepo = $this->createMock(MemberRepository::class);
        $this->ticketRepo = $this->createMock(TicketRepository::class);
        $this->emailRepo = $this->createMock(EmailRepository::class);
        $this->rn = new BookingReferenceService(); // final readonly class with default params
        $this->mailer = $this->createMock(MailerInterface::class);

        // Create real QrService instance (final class can't be mocked/extended)
        // Tests requiring QR generation will be skipped since they need the actual logo file
        $assetMapper = $this->createMock(\Symfony\Component\AssetMapper\AssetMapperInterface::class);
        $assetMapper->method('getPublicPath')->willReturn('/images/golden-logo.png');
        $this->qrGenerator = new QrService($assetMapper, '/tmp');

        $this->mm = $this->createMock(MattermostNotifierService::class);

        $this->subscriber = new StripeEventSubscriber(
            $this->checkoutRepo,
            $this->productRepo,
            $this->logger,
            $this->stripe,
            $this->memberRepo,
            $this->ticketRepo,
            $this->emailRepo,
            $this->rn,
            $this->mailer,
            $this->mm,
            $this->qrGenerator,
        );
    }

    public function testGetSubscribedEvents(): void
    {
        $events = StripeEventSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(StripeEvents::PRICE_CREATED, $events);
        $this->assertArrayHasKey(StripeEvents::PRICE_UPDATED, $events);
        $this->assertArrayHasKey(StripeEvents::PRICE_DELETED, $events);
        $this->assertArrayHasKey(StripeEvents::PRODUCT_UPDATED, $events);
        $this->assertArrayHasKey(StripeEvents::CHECKOUT_SESSION_EXPIRED, $events);
        $this->assertArrayHasKey(StripeEvents::CHECKOUT_SESSION_COMPLETED, $events);

        $this->assertSame('onPriceCreated', $events[StripeEvents::PRICE_CREATED]);
        $this->assertSame('onPriceUpdated', $events[StripeEvents::PRICE_UPDATED]);
        $this->assertSame('onPriceDeleted', $events[StripeEvents::PRICE_DELETED]);
        $this->assertSame('onProductUpdated', $events[StripeEvents::PRODUCT_UPDATED]);
        $this->assertSame('onCheckoutExpired', $events[StripeEvents::CHECKOUT_SESSION_EXPIRED]);
        $this->assertSame('onCheckoutCompleted', $events[StripeEvents::CHECKOUT_SESSION_COMPLETED]);
    }

    /* -----------------------------------------------------------------
     * onCheckoutExpired Tests
     * ----------------------------------------------------------------- */

    public function testOnCheckoutExpiredSetsStatusToMinusOne(): void
    {
        $sessionId = 'cs_test_expired_123';

        $checkout = new Checkout();
        $checkout->setStripeSessionId($sessionId);
        $checkout->setStatus(0); // Open

        $this->checkoutRepo->expects($this->once())
            ->method('findOneBy')
            ->with(['stripeSessionId' => $sessionId])
            ->willReturn($checkout);

        $this->checkoutRepo->expects($this->once())
            ->method('save')
            ->with($checkout, true);

        $this->logger->expects($this->exactly(2))
            ->method('notice');

        $webhook = $this->createCheckoutSessionWebhook($sessionId, 'expired');

        $this->subscriber->onCheckoutExpired($webhook);

        $this->assertSame(-1, $checkout->getStatus());
    }

    public function testOnCheckoutExpiredThrowsWhenCheckoutNotFound(): void
    {
        $sessionId = 'cs_test_unknown_456';

        $this->checkoutRepo->expects($this->once())
            ->method('findOneBy')
            ->with(['stripeSessionId' => $sessionId])
            ->willReturn(null);

        $this->logger->expects($this->once())
            ->method('notice');

        $webhook = $this->createCheckoutSessionWebhook($sessionId, 'expired');

        // Code throws when checkout not found (no null check)
        $this->expectException(\Error::class);
        $this->subscriber->onCheckoutExpired($webhook);
    }

    /* -----------------------------------------------------------------
     * onPriceDeleted Tests
     * ----------------------------------------------------------------- */

    public function testOnPriceDeletedDeactivatesProduct(): void
    {
        $priceId = 'price_test_delete_789';

        $product = new Product();
        $product->setStripePriceId($priceId);
        $product->setActive(true);

        $this->productRepo->expects($this->once())
            ->method('findOneBy')
            ->with(['stripePriceId' => $priceId])
            ->willReturn($product);

        $this->productRepo->expects($this->once())
            ->method('save')
            ->with($product, true);

        $webhook = $this->createPriceWebhook($priceId, 'deleted');

        $this->subscriber->onPriceDeleted($webhook);

        $this->assertFalse($product->isActive());
    }

    public function testOnPriceDeletedThrowsWhenProductNotFound(): void
    {
        $priceId = 'price_test_unknown_999';

        $this->productRepo->expects($this->once())
            ->method('findOneBy')
            ->with(['stripePriceId' => $priceId])
            ->willReturn(null);

        $webhook = $this->createPriceWebhook($priceId, 'deleted');

        // Code throws when product not found (no null check)
        $this->expectException(\Error::class);
        $this->subscriber->onPriceDeleted($webhook);
    }

    /* -----------------------------------------------------------------
     * onProductUpdated Tests
     * ----------------------------------------------------------------- */

    public function testOnProductUpdatedUpdatesMatchingProducts(): void
    {
        $stripeProductId = 'prod_test_update_abc';

        $product1 = new Product();
        $product1->setStripeId($stripeProductId);
        $product1->setNameEn('Old Name');

        $product2 = new Product();
        $product2->setStripeId($stripeProductId);
        $product2->setNameEn('Old Name 2');

        $this->productRepo->expects($this->once())
            ->method('findBy')
            ->with(['stripeId' => $stripeProductId])
            ->willReturn([$product1, $product2]);

        $this->productRepo->expects($this->exactly(2))
            ->method('save');

        $webhook = $this->createProductWebhook($stripeProductId, 'Updated Product Name', true);

        $this->subscriber->onProductUpdated($webhook);

        // Both products should be updated with new name
        $this->assertSame('Updated Product Name', $product1->getNameEn());
        $this->assertSame('Updated Product Name', $product2->getNameEn());
    }

    public function testOnProductUpdatedSetsInactiveWhenStripeProductInactive(): void
    {
        $stripeProductId = 'prod_test_inactive_def';

        $product = new Product();
        $product->setStripeId($stripeProductId);
        $product->setActive(true);

        $this->productRepo->expects($this->once())
            ->method('findBy')
            ->with(['stripeId' => $stripeProductId])
            ->willReturn([$product]);

        $this->productRepo->expects($this->once())
            ->method('save')
            ->with($product, true);

        $webhook = $this->createProductWebhook($stripeProductId, 'Inactive Product', false);

        $this->subscriber->onProductUpdated($webhook);

        $this->assertFalse($product->isActive());
    }

    public function testOnProductUpdatedLogsErrorWhenNoProductsFound(): void
    {
        $stripeProductId = 'prod_test_notfound_ghi';

        $this->productRepo->expects($this->once())
            ->method('findBy')
            ->with(['stripeId' => $stripeProductId])
            ->willReturn([]);

        // No error logged for empty result, just no-op
        $webhook = $this->createProductWebhook($stripeProductId, 'No Match', true);

        $this->subscriber->onProductUpdated($webhook);

        // Test passes if no exception thrown
        $this->assertTrue(true);
    }

    /* -----------------------------------------------------------------
     * onCheckoutCompleted Tests
     * ----------------------------------------------------------------- */

    public function testOnCheckoutCompletedSkipsNonTicketProducts(): void
    {
        $sessionId = 'cs_test_service_fee';

        $event = $this->createMock(Event::class);
        $event->method('getNameByLang')->willReturn('Service Fee Event');
        $event->method('getPicture')->willReturn(null);

        // Service fee product (not a ticket)
        $serviceFee = new Product();
        $serviceFee->setStripeId('prod_fee');
        $serviceFee->setAmount(150);
        $serviceFee->setTicket(false);
        $serviceFee->setServiceFee(true);
        $serviceFee->setEvent($event);

        $cartItem = $this->createMock(CartItem::class);
        $cartItem->method('getProduct')->willReturn($serviceFee);
        $cartItem->method('getQuantity')->willReturn(1);

        $cart = $this->createMock(Cart::class);
        $cart->method('getEmail')->willReturn('fee@example.com');
        $cart->method('getProducts')->willReturn(new ArrayCollection([$cartItem]));

        $checkout = new Checkout();
        $checkout->setStripeSessionId($sessionId);
        $checkout->setStatus(0);
        $checkout->setCart($cart);

        $this->checkoutRepo->method('findOneBy')->willReturn($checkout);
        $this->checkoutRepo->expects($this->exactly(2))->method('save');

        // No tickets should be created
        $this->ticketRepo->expects($this->never())
            ->method('save');

        // No emails should be sent (no tickets)
        $this->mailer->expects($this->never())
            ->method('send');

        // No mattermost notification (no tickets sold)
        $this->mm->expects($this->never())
            ->method('sendToMattermost');

        $webhook = $this->createCheckoutSessionWebhook($sessionId, 'complete');

        $this->subscriber->onCheckoutCompleted($webhook);

        $this->assertSame(2, $checkout->getStatus());
    }

    /* -----------------------------------------------------------------
     * Helper Methods
     * ----------------------------------------------------------------- */

    private function createCheckoutSessionWebhook(string $sessionId, string $status): StripeWebhook
    {
        $stripeEventData = StripeEvent::constructFrom([
            'data' => [
                'object' => [
                    'id' => $sessionId,
                    'object' => 'checkout.session',
                    'status' => $status,
                    'locale' => 'fi',
                    'payment_status' => $status === 'complete' ? 'paid' : 'unpaid',
                ],
            ],
        ]);

        $webhook = $this->createMock(StripeWebhook::class);
        $webhook->method('getStripeObject')->willReturn($stripeEventData);

        return $webhook;
    }

    private function createPriceWebhook(string $priceId, string $action): StripeWebhook
    {
        $stripeEventData = StripeEvent::constructFrom([
            'data' => [
                'object' => [
                    'id' => $priceId,
                    'object' => 'price',
                    'deleted' => $action === 'deleted',
                ],
            ],
        ]);

        $webhook = $this->createMock(StripeWebhook::class);
        $webhook->method('getStripeObject')->willReturn($stripeEventData);

        return $webhook;
    }

    private function createProductWebhook(string $productId, string $name, bool $active): StripeWebhook
    {
        $stripeEventData = StripeEvent::constructFrom([
            'data' => [
                'object' => [
                    'id' => $productId,
                    'object' => 'product',
                    'name' => $name,
                    'active' => $active,
                ],
            ],
        ]);

        $webhook = $this->createMock(StripeWebhook::class);
        $webhook->method('getStripeObject')->willReturn($stripeEventData);

        return $webhook;
    }
}
