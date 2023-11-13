<?php

namespace App\EventSubscriber;

use App\Entity\Event;
use App\Entity\Product;
use App\Entity\Ticket;
use App\Helper\AppStripeClient;
use App\Helper\ReferenceNumber;
use App\Repository\CheckoutRepository;
use App\Repository\MemberRepository;
use App\Repository\ProductRepository;
use App\Repository\TicketRepository;
use Fpt\StripeBundle\Event\StripeEvents;
use Fpt\StripeBundle\Event\StripeWebhook;
use Psr\Log\LoggerInterface;
use SimpleSoftwareIO\QrCode\Generator;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Part\DataPart;

class StripeEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CheckoutRepository $cRepo,
        private readonly ProductRepository $pRepo,
        private readonly LoggerInterface $logger,
        private readonly AppStripeClient $stripe,
        private readonly MemberRepository $memberRepo,
        private readonly TicketRepository $ticketRepo,
        private readonly ReferenceNumber $rn,
        private readonly MailerInterface $mailer
    ) {
    }
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
            $products = $this->pRepo->findBy(['stripeId' => $stripeProduct['id']]);
            foreach ($products as $product) {
                $product = $this->stripe->updateOurProduct($product, null, $stripeProduct);
                $this->pRepo->save($product, true);
            }
        } catch (\Exception $e) {
            $this->logger->error('Code: ' . $e->getCode() . ' M:' . $e->getMessage());
        }
    }
    public function onPriceCreated(StripeWebhook $webhook): void
    {
        $stripeEvent = $webhook->getStripeObject();
        $stripePrice = $stripeEvent->data->object;

        try {
            $product = new Product();
            $product = $this->stripe->updateOurProduct($product, $stripePrice, null);
            $this->pRepo->save($product, true);
        } catch (\Exception $e) {
            $this->logger->error('Code: ' . $e->getCode() . ' M:' . $e->getMessage());
        }
    }
    public function onPriceUpdated(StripeWebhook $webhook): void
    {
        $stripeEvent = $webhook->getStripeObject();
        $stripePrice = $stripeEvent->data->object;
        try {
            $product = $this->pRepo->findOneBy(['stripePriceId' => $stripePrice->id]);
            $product = $this->stripe->updateOurProduct($product, $stripePrice, null);
            $this->pRepo->save($product, true);
        } catch (\Exception $e) {
            $this->logger->error('Code: ' . $e->getCode() . ' M:' . $e->getMessage());
        }
    }
    public function onPriceDeleted(StripeWebhook $webhook): void
    {
        $stripeEvent = $webhook->getStripeObject();
        $stripePrice = $stripeEvent->data->object;

        try {
            $product = $this->pRepo->findOneBy(['stripePriceId' => $stripePrice->id]);
            $product->setActive(false);
            $this->pRepo->save($product, true);
        } catch (\Exception $e) {
            $this->logger->error('Code: ' . $e->getCode() . ' M:' . $e->getMessage());
        }
    }
    public function onCheckoutExpired(StripeWebhook $webhook): void
    {
        $stripeEvent = $webhook->getStripeObject();
        $session = $stripeEvent->data->object;
        $this->logger->notice('Session: ' . $session['id']);
        try {
            $checkout = $this->cRepo->findOneBy(['stripeSessionId' => $session['id']]);
            $this->logger->notice('Checkout expired: ' . $checkout->getStripeSessionId());
            $checkout->setStatus(-1);
            $this->cRepo->save($checkout, true);
        } catch (\Exception $e) {
            $this->logger->error('Code: ' . $e->getCode() . ' M:' . $e->getMessage());
        }
    }
    public function onCheckoutCompleted(StripeWebhook $webhook): void
    {
        $stripeEvent = $webhook->getStripeObject();
        $session = $stripeEvent->data->object;
        $this->logger->notice('Session: ' . $session['id']);
        try {
            $checkout = $this->cRepo->findOneBy(['stripeSessionId' => $session['id']]);
            $this->logger->notice('Checkout done: ' . $checkout->getStripeSessionId());
            $checkout->setStatus(1);
            $this->cRepo->save($checkout, true);
            if ($checkout->getStatus() == 1) {
                $cart = $checkout->getCart();
                $email = $cart->getEmail();
                $locale = $session['locale'];
                $products = $cart->getProducts();
                $tickets = [];
                foreach ($products as $cartItem) {
                    $product = $cartItem->getProduct();
                    $event = $product->getEvent();
                    $quantity = $cartItem->getQuantity();
                    if ($product->isTicket()) {
                        $tickets = $this->giveEventTicketToEmail($event, $product, $quantity, $email);
                    }
                }
                $qrGenerator = new Generator();
                foreach ($tickets as $ticket) {
                    $qrs[] = $qrGenerator
                        ->format('png')
                        ->eye('circle')
                        ->style('round')
                        ->size(600)
                        ->gradient(0, 40, 40, 40, 40, 0, 'radial')
                        ->errorCorrection('H')
                        ->merge('images/golden-logo.png', .2)
                        ->generate((string)$ticket->getReferenceNumber());
                }
                try {
                    $this->sendTicketQrEmail(
                        $event->getNameByLang($locale),
                        $email,
                        $qrs,
                        $event->getPicture()
                    );
                } catch (\Exception $e) {
                    $this->logger->error('Code: ' . $e->getCode() . ' M:' . $e->getMessage());
                }
                $checkout->setStatus(2);
                $this->cRepo->save($checkout, true);
            }
        } catch (\Exception $e) {
            $this->logger->error('Code: ' . $e->getCode() . ' M:' . $e->getMessage());
        }
    }
    private function sendTicketQrEmail($eventName, $to, $qrs, $img)
    {
        foreach ($qrs as $x => $qr) {
            $mail =  (new TemplatedEmail())
                ->from(new Address('webmaster@entropy.fi', 'Entropy ry'))
                ->to($to)
                ->replyTo('info@entropy.fi')
                ->subject('[' . $eventName . '] Your ticket #' . ($x + 1) . ' / Lippusi #' . ($x + 1))
                ->addPart((new DataPart($qr, 'ticket', 'image/png', 'base64'))->asInline())
                ->htmlTemplate('emails/ticket.html.twig')
                ->context(['body' => $qr, 'links' => true, 'img' => $img]);
            $this->mailer->send($mail);
        }
    }
    private function giveEventTicketToEmail(
        Event $event,
        Product $product,
        int $quantity,
        $email
    ): array {
        $tickets = [];
        // check if is members email
        $member = $this->memberRepo->getByEmail($email);
        for ($i = 1; $i <= $quantity; $i++) {
            $ticket = new Ticket();
            $ticket->setEvent($event);
            $ticket->setStripeProductId($product->getStripeId());
            $ticket->setPrice($product->getAmount());
            $ticket->setStatus('paid');
            $ticket->setEmail($email);
            if (!is_null($member)) {
                $ticket->setOwner($member);
            }
            $this->ticketRepo->save($ticket, true);
            $ticket->setReferenceNumber($this->rn->calculateReferenceNumber($ticket, 9000, 909));
            $this->ticketRepo->save($ticket, true);
            $tickets[] = $ticket;
        }
        return $tickets;
    }
}
