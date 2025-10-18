<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\Event;
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
use Fpt\StripeBundle\Event\StripeEvents;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
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
    private QrService $qrGenerator;

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

        // Create real instances for final services with mocked dependencies
        $assetMapper = $this->createMock(\Symfony\Component\AssetMapper\AssetMapperInterface::class);
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

    /*
     * Comprehensive testing of webhook handler methods requires:
     * - Stripe\Event objects (third-party library, complex to mock)
     * - Multiple final service classes (StripeService, QrService, BookingReferenceService)
     * - Integration with Stripe API
     *
     * These tests are better suited for integration/functional testing with:
     * - Stripe webhook fixtures
     * - Test Stripe API keys
     * - Full application container
     *
     * For unit testing, we verify the event subscription configuration is correct.
     * The webhook handlers are covered by functional tests in tests/Functional/.
     */
}
