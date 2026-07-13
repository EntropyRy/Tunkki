<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Checkout;
use App\Entity\Event;
use App\Entity\Member;
use App\Entity\Product;
use App\EventSubscriber\StripeEventSubscriber;
use App\Repository\CheckoutRepository;
use App\Repository\MemberRepository;
use App\Repository\ProductRepository;
use App\Repository\TicketRepository;
use App\Service\Email\EmailService;
use App\Service\MattermostNotifierService;
use App\Service\QrService;
use App\Service\Rental\Booking\BookingReferenceService;
use App\Service\StripeServiceInterface;
use App\Webhook\StripeEventNames;
use App\Webhook\StripeWebhookEvent;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Stripe\Event as StripeEvent;
use Symfony\Component\AssetMapper\AssetMapperInterface;

final class StripeEventSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $events = StripeEventSubscriber::getSubscribedEvents();

        self::assertSame(
            [
                StripeEventNames::PRICE_CREATED => 'onPriceCreated',
                StripeEventNames::PRICE_UPDATED => 'onPriceUpdated',
                StripeEventNames::PRICE_DELETED => 'onPriceDeleted',
                StripeEventNames::PRODUCT_UPDATED => 'onProductUpdated',
                StripeEventNames::CHECKOUT_SESSION_EXPIRED => 'onCheckoutExpired',
                StripeEventNames::CHECKOUT_SESSION_COMPLETED => 'onCheckoutCompleted',
            ],
            $events,
        );
    }

    public function testGiveEventTicketToEmailAssignsOwnerWhenMemberFound(): void
    {
        $member = new Member();
        $member->setEmail('member@example.test');

        $memberRepo = $this->createStub(MemberRepository::class);
        $memberRepo->method('getByEmail')->willReturn($member);

        $ticketRepo = $this->createMock(TicketRepository::class);
        $ticketRepo->expects($this->exactly(2))->method('save');

        $subscriber = $this->createSubscriber([
            'memberRepo' => $memberRepo,
            'ticketRepo' => $ticketRepo,
        ]);

        $checkout = new Checkout();
        $event = new Event();
        $product = (new Product())
            ->setStripeId('prod_test_123')
            ->setAmount(2000)
            ->setTicket(true)
            ->setNameEn('Ticket EN')
            ->setNameFi('Ticket FI')
            ->setEvent($event);

        $tickets = $subscriber->giveEventTicketToEmailPublic(
            $checkout,
            $event,
            $product,
            1,
            'buyer@example.test',
            'en',
        );

        self::assertCount(1, $tickets);
        self::assertSame($member, $tickets[0]->getOwner());
        self::assertSame(2000, $tickets[0]->getPrice());
        self::assertSame('Ticket EN', $tickets[0]->getName());
        self::assertSame('prod_test_123', $tickets[0]->getStripeProductId());
        self::assertNotSame('', (string) $tickets[0]->getReferenceNumber());
    }

    public function testGiveEventTicketToEmailSkipsOwnerWhenMemberMissing(): void
    {
        $memberRepo = $this->createStub(MemberRepository::class);
        $memberRepo->method('getByEmail')->willReturn(null);

        $ticketRepo = $this->createMock(TicketRepository::class);
        $ticketRepo->expects($this->exactly(2))->method('save');

        $subscriber = $this->createSubscriber([
            'memberRepo' => $memberRepo,
            'ticketRepo' => $ticketRepo,
        ]);

        $checkout = new Checkout();
        $event = new Event();
        $product = (new Product())
            ->setStripeId('prod_test_456')
            ->setAmount(1500)
            ->setTicket(true)
            ->setNameEn('Ticket EN')
            ->setEvent($event);

        $tickets = $subscriber->giveEventTicketToEmailPublic(
            $checkout,
            $event,
            $product,
            1,
            'buyer@example.test',
            'en',
        );

        self::assertCount(1, $tickets);
        self::assertNull($tickets[0]->getOwner());
    }

    public function testSendTicketQrEmailDelegatesToEmailService(): void
    {
        $emailService = $this->createMock(EmailService::class);
        $emailService->expects($this->once())
            ->method('sendTicketQrEmails')
            ->with(
                $this->isInstanceOf(Event::class),
                'buyer@example.test',
                [['qr' => 'qr-data', 'name' => 'Ticket']],
                null,
            );

        $subscriber = $this->createSubscriber(['emailService' => $emailService]);
        $event = new Event();

        $subscriber->sendTicketQrEmailPublic(
            $event,
            'Event Name',
            'buyer@example.test',
            [['qr' => 'qr-data', 'name' => 'Ticket']],
            null,
        );
    }

    public function testOnCheckoutCompletedSetsStatusToTwoEvenWhenEmailSendingFails(): void
    {
        $event = new Event();
        $event->setName('Test Event');
        $event->setNimi('Testitapahtuma');

        $product = (new Product())
            ->setStripeId('prod_test_email_fail')
            ->setAmount(1000)
            ->setTicket(true)
            ->setNameEn('Ticket EN')
            ->setNameFi('Ticket FI')
            ->setEvent($event);

        $cartItem = (new CartItem())
            ->setProduct($product)
            ->setQuantity(1);

        $cart = new Cart();
        $cart->setEmail('buyer@example.test');
        $cart->addProduct($cartItem);

        $checkout = new Checkout();
        $checkout->setStripeSessionId('cs_test_email_fail');
        $checkout->setCart($cart);
        // Pre-set so the Stripe API isn't consulted for a receipt URL.
        $checkout->setReceiptUrl('https://stripe.example/receipt');

        $checkoutRepo = $this->createStub(CheckoutRepository::class);
        $checkoutRepo->method('findOneBy')->willReturn($checkout);

        $emailService = $this->createStub(EmailService::class);
        $emailService->method('sendTicketQrEmails')->willThrowException(
            new \RuntimeException('smtp down'),
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to send ticket QR email'));

        $mm = $this->createMock(MattermostNotifierService::class);
        $mm->expects($this->once())->method('sendToMattermost');

        $assetMapper = $this->createStub(AssetMapperInterface::class);
        // Force QrService's public-path lookup to miss, so it falls back
        // to the real logo file under assets/.
        $assetMapper->method('getPublicPath')->willReturn('/missing-logo.png');
        $qr = new QrService($assetMapper, \dirname(__DIR__, 3));

        $subscriber = $this->createSubscriber([
            'checkoutRepo' => $checkoutRepo,
            'emailService' => $emailService,
            'logger' => $logger,
            'mm' => $mm,
            'qr' => $qr,
        ]);

        $webhook = $this->createWebhook([
            'id' => 'cs_test_email_fail',
            'locale' => 'en',
        ]);

        $subscriber->onCheckoutCompleted($webhook);

        self::assertSame(
            2,
            $checkout->getStatus(),
            'Checkout must be marked processed (status 2) even when the confirmation email fails to send',
        );
    }

    public function testOnPriceCreatedLogsErrorsFromStripeService(): void
    {
        $stripe = $this->createStub(StripeServiceInterface::class);
        $stripe->method('updateOurProduct')->willThrowException(new \RuntimeException('stripe fail'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->callback(static function (string $message): bool {
                return str_contains($message, 'M:stripe fail');
            }));

        $subscriber = $this->createSubscriber([
            'stripe' => $stripe,
            'logger' => $logger,
        ]);

        $webhook = $this->createWebhook(['id' => 'price_test_1']);

        $subscriber->onPriceCreated($webhook);
    }

    public function testOnPriceDeletedLogsErrorsFromRepository(): void
    {
        $productRepo = $this->createStub(ProductRepository::class);
        $productRepo->method('findOneBy')->willThrowException(new \RuntimeException('repo fail'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->callback(static function (string $message): bool {
                return str_contains($message, 'M:repo fail');
            }));

        $subscriber = $this->createSubscriber([
            'productRepo' => $productRepo,
            'logger' => $logger,
        ]);

        $webhook = $this->createWebhook((object) ['id' => 'price_test_2']);

        $subscriber->onPriceDeleted($webhook);
    }

    private function createSubscriber(array $overrides = []): TestableStripeEventSubscriber
    {
        return new TestableStripeEventSubscriber(
            $overrides['checkoutRepo'] ?? $this->createStub(CheckoutRepository::class),
            $overrides['productRepo'] ?? $this->createStub(ProductRepository::class),
            $overrides['logger'] ?? $this->createStub(LoggerInterface::class),
            $overrides['stripe'] ?? $this->createStub(StripeServiceInterface::class),
            $overrides['memberRepo'] ?? $this->createStub(MemberRepository::class),
            $overrides['ticketRepo'] ?? $this->createStub(TicketRepository::class),
            $overrides['emailService'] ?? $this->createStub(EmailService::class),
            $overrides['rn'] ?? new BookingReferenceService(),
            $overrides['mm'] ?? $this->createStub(MattermostNotifierService::class),
            $overrides['qr'] ?? new QrService(
                $this->createStub(AssetMapperInterface::class),
                '/tmp',
            ),
        );
    }

    private function createWebhook(object|array $stripeObject): StripeWebhookEvent
    {
        $event = StripeEvent::constructFrom([
            'id' => 'evt_test_'.bin2hex(random_bytes(4)),
            'object' => 'event',
            'data' => [
                'object' => $stripeObject,
            ],
        ]);

        return new StripeWebhookEvent($event);
    }
}

final class TestableStripeEventSubscriber extends StripeEventSubscriber
{
    public function sendTicketQrEmailPublic(
        Event $event,
        string $eventName,
        string $to,
        array $qrs,
        ?\App\Entity\Sonata\SonataMediaMedia $img,
    ): void {
        $this->sendTicketQrEmail($event, $eventName, $to, $qrs, $img);
    }

    public function giveEventTicketToEmailPublic(
        Checkout $checkout,
        Event $event,
        Product $product,
        int $quantity,
        string $email,
        string $locale,
    ): array {
        return $this->giveEventTicketToEmail(
            $checkout,
            $event,
            $product,
            $quantity,
            $email,
            $locale,
        );
    }
}
