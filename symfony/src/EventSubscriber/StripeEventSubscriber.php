<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Checkout;
use App\Entity\Event;
use App\Entity\Product;
use App\Entity\Sonata\SonataMediaMedia;
use App\Entity\Ticket;
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
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class StripeEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CheckoutRepository $checkoutRepo,
        private readonly ProductRepository $productRepo,
        private readonly LoggerInterface $logger,
        private readonly StripeServiceInterface $stripe,
        private readonly MemberRepository $memberRepo,
        private readonly TicketRepository $ticketRepo,
        private readonly EmailService $emailService,
        private readonly BookingReferenceService $rn,
        private readonly MattermostNotifierService $mm,
        private readonly QrService $qrGenerator,
    ) {
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            StripeEvents::PRICE_CREATED => 'onPriceCreated',
            StripeEvents::PRICE_UPDATED => 'onPriceUpdated',
            StripeEvents::PRICE_DELETED => 'onPriceDeleted',
            StripeEvents::PRODUCT_UPDATED => 'onProductUpdated',
            StripeEvents::CHECKOUT_SESSION_EXPIRED => 'onCheckoutExpired',
            StripeEvents::CHECKOUT_SESSION_COMPLETED => 'onCheckoutCompleted',
        ];
    }

    public function onProductUpdated(StripeWebhook $webhook): void
    {
        $stripeEvent = $webhook->getStripeObject();
        $stripeProduct = $stripeEvent->data->object;
        try {
            $products = $this->productRepo->findBy([
                'stripeId' => $stripeProduct['id'],
            ]);
            foreach ($products as $product) {
                $product = $this->stripe->updateOurProduct(
                    $product,
                    null,
                    $stripeProduct,
                );
                $this->productRepo->save($product, true);
            }
        } catch (\Exception $e) {
            $this->logger->error(
                'Code: '.$e->getCode().' M:'.$e->getMessage(),
            );
        }
    }

    public function onPriceCreated(StripeWebhook $webhook): void
    {
        $stripeEvent = $webhook->getStripeObject();
        $stripePrice = $stripeEvent->data->object;

        try {
            $product = new Product();
            $product = $this->stripe->updateOurProduct(
                $product,
                $stripePrice,
                null,
            );
            $this->productRepo->save($product, true);
        } catch (\Exception $e) {
            $this->logger->error(
                'Code: '.$e->getCode().' M:'.$e->getMessage(),
            );
        }
    }

    public function onPriceUpdated(StripeWebhook $webhook): void
    {
        $stripeEvent = $webhook->getStripeObject();
        $stripePrice = $stripeEvent->data->object;
        try {
            $product = $this->productRepo->findOneBy([
                'stripePriceId' => $stripePrice->id,
            ]);
            $product = $this->stripe->updateOurProduct(
                $product,
                $stripePrice,
                null,
            );
            $this->productRepo->save($product, true);
        } catch (\Exception $e) {
            $this->logger->error(
                'Code: '.$e->getCode().' M:'.$e->getMessage(),
            );
        }
    }

    public function onPriceDeleted(StripeWebhook $webhook): void
    {
        $stripeEvent = $webhook->getStripeObject();
        $stripePrice = $stripeEvent->data->object;

        try {
            $product = $this->productRepo->findOneBy([
                'stripePriceId' => $stripePrice->id,
            ]);
            $product->setActive(false);
            $this->productRepo->save($product, true);
        } catch (\Exception $e) {
            $this->logger->error(
                'Code: '.$e->getCode().' M:'.$e->getMessage(),
            );
        }
    }

    public function onCheckoutExpired(StripeWebhook $webhook): void
    {
        $stripeEvent = $webhook->getStripeObject();
        $session = $stripeEvent->data->object;
        $this->logger->notice('Session: '.$session['id']);
        try {
            $checkout = $this->checkoutRepo->findOneBy([
                'stripeSessionId' => $session['id'],
            ]);
            $this->logger->notice(
                'Checkout expired: '.$checkout->getStripeSessionId(),
            );
            $checkout->setStatus(-1);
            $this->checkoutRepo->save($checkout, true);
        } catch (\Exception $e) {
            $this->logger->error(
                'Code: '.$e->getCode().' M:'.$e->getMessage(),
            );
        }
    }

    public function onCheckoutCompleted(StripeWebhook $webhook): void
    {
        $stripeEvent = $webhook->getStripeObject();
        $session = $stripeEvent->data->object;
        $this->logger->notice('Session: '.$session['id']);
        try {
            $checkout = $this->checkoutRepo->findOneBy([
                'stripeSessionId' => $session['id'],
            ]);
            $this->logger->notice(
                'Checkout done: '.$checkout->getStripeSessionId(),
            );
            $checkout->setStatus(1);
            if (null === $checkout->getReceiptUrl()) {
                $checkout->setReceiptUrl(
                    $this->stripe->getReceiptUrlForSessionId($session['id']),
                );
            }
            $this->checkoutRepo->save($checkout, true);
            if (1 == $checkout->getStatus()) {
                $cart = $checkout->getCart();
                $email = $cart->getEmail();
                $locale = $session['locale'];
                $products = $cart->getProducts();
                $tickets = [];
                $qrs = [];
                $event = null;
                foreach ($products as $cartItem) {
                    $product = $cartItem->getProduct();
                    $event = $product->getEvent();
                    $quantity = $cartItem->getQuantity();
                    if ($product->isTicket()) {
                        $given = $this->giveEventTicketToEmail(
                            $checkout,
                            $event,
                            $product,
                            $quantity,
                            $email,
                            $locale,
                        );
                        $tickets = [...$tickets, ...$given];
                    }
                }
                if ([] !== $tickets && $event) {
                    $qrGenerator = $this->qrGenerator;
                    foreach ($tickets as $ticket) {
                        $qrs[] = [
                            'qr' => $qrGenerator->getQr(
                                (string) $ticket->getReferenceNumber(),
                            ),
                            'name' => $ticket->getName() ?? 'Ticket',
                        ];
                    }
                    $this->sendTicketQrEmail(
                        $event,
                        $event->getNameByLang($locale),
                        $email,
                        $qrs,
                        $event->getPicture(),
                    );
                }
                $checkout->setStatus(2);
                $this->checkoutRepo->save($checkout, true);
                foreach ($products as $cartItem) {
                    $product = $cartItem->getProduct();
                    if ($product->isTicket()) {
                        $sold =
                            $cartItem->getQuantity() > 1
                                ? 'Sold '.
                                    $cartItem->getQuantity().
                                    ' tickets.'
                                : 'Sold 1 ticket.';
                        $this->mm->sendToMattermost(
                            '['.
                                $product->getNameEn().
                                '] '.
                                $sold.
                                ' Total:'.
                                $product->getSold().
                                '/'.
                                $product->getQuantity(),
                            'yhdistys',
                        );
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->error(
                'Code: '.$e->getCode().' M:'.$e->getMessage(),
            );
        }
    }

    protected function sendTicketQrEmail(
        Event $event,
        string $eventName,
        string $to,
        array $qrs,
        ?SonataMediaMedia $img,
    ): void {
        $this->emailService->sendTicketQrEmails($event, $to, $qrs, $img);
    }

    protected function giveEventTicketToEmail(
        Checkout $checkout,
        Event $event,
        Product $product,
        int $quantity,
        string $email,
        string $locale,
    ): array {
        $tickets = [];
        // check if is members email
        $member = $this->memberRepo->getByEmail($email);
        for ($i = 1; $i <= $quantity; ++$i) {
            $ticket = new Ticket();
            $ticket->setName($product->getName($locale) ?? 'Ticket');
            $ticket->setEvent($event);
            $ticket->setCheckout($checkout);
            $ticket->setStripeProductId($product->getStripeId());
            $ticket->setPrice($product->getAmount());
            $ticket->setStatus('paid');
            $ticket->setEmail($email);
            if (null !== $member) {
                $ticket->setOwner($member);
            }
            $this->ticketRepo->save($ticket, true);
            $ticket->setReferenceNumber(
                $this->rn->calculateReferenceNumber($ticket, 9000, 909),
            );
            $this->ticketRepo->save($ticket, true);
            $tickets[] = $ticket;
        }

        return $tickets;
    }
}
