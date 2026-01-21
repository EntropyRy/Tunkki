<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

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
use Fpt\StripeBundle\Event\StripeEvents;
use Fpt\StripeBundle\Event\StripeWebhook;
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
                StripeEvents::PRICE_CREATED => 'onPriceCreated',
                StripeEvents::PRICE_UPDATED => 'onPriceUpdated',
                StripeEvents::PRICE_DELETED => 'onPriceDeleted',
                StripeEvents::PRODUCT_UPDATED => 'onProductUpdated',
                StripeEvents::CHECKOUT_SESSION_EXPIRED => 'onCheckoutExpired',
                StripeEvents::CHECKOUT_SESSION_COMPLETED => 'onCheckoutCompleted',
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

    private function createWebhook(object|array $stripeObject): StripeWebhook
    {
        $event = StripeEvent::constructFrom([
            'id' => 'evt_test_'.bin2hex(random_bytes(4)),
            'object' => 'event',
            'data' => [
                'object' => $stripeObject,
            ],
        ]);

        $webhook = $this->createStub(StripeWebhook::class);
        $webhook->method('getStripeObject')->willReturn($event);

        return $webhook;
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
