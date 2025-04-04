<?php

namespace App\EventSubscriber;

use App\Entity\Event;
use App\Entity\Product;
use App\Entity\Sonata\SonataMediaMedia;
use App\Entity\Ticket;
use App\Helper\AppStripeClient;
use App\Helper\Mattermost;
use App\Helper\ReferenceNumber;
use App\Helper\Qr;
use App\Repository\CheckoutRepository;
use App\Repository\EmailRepository;
use App\Repository\MemberRepository;
use App\Repository\ProductRepository;
use App\Repository\TicketRepository;
use Fpt\StripeBundle\Event\StripeEvents;
use Fpt\StripeBundle\Event\StripeWebhook;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Part\DataPart;

class StripeEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CheckoutRepository $checkoutRepo,
        private readonly ProductRepository $productRepo,
        private readonly LoggerInterface $logger,
        private readonly AppStripeClient $stripe,
        private readonly MemberRepository $memberRepo,
        private readonly TicketRepository $ticketRepo,
        private readonly EmailRepository $emailRepo,
        private readonly ReferenceNumber $rn,
        private readonly MailerInterface $mailer,
        private readonly Mattermost $mm
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
            $products = $this->productRepo->findBy(['stripeId' => $stripeProduct['id']]);
            foreach ($products as $product) {
                $product = $this->stripe->updateOurProduct($product, null, $stripeProduct);
                $this->productRepo->save($product, true);
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
            $this->productRepo->save($product, true);
        } catch (\Exception $e) {
            $this->logger->error('Code: ' . $e->getCode() . ' M:' . $e->getMessage());
        }
    }
    public function onPriceUpdated(StripeWebhook $webhook): void
    {
        $stripeEvent = $webhook->getStripeObject();
        $stripePrice = $stripeEvent->data->object;
        try {
            $product = $this->productRepo->findOneBy(['stripePriceId' => $stripePrice->id]);
            $product = $this->stripe->updateOurProduct($product, $stripePrice, null);
            $this->productRepo->save($product, true);
        } catch (\Exception $e) {
            $this->logger->error('Code: ' . $e->getCode() . ' M:' . $e->getMessage());
        }
    }
    public function onPriceDeleted(StripeWebhook $webhook): void
    {
        $stripeEvent = $webhook->getStripeObject();
        $stripePrice = $stripeEvent->data->object;

        try {
            $product = $this->productRepo->findOneBy(['stripePriceId' => $stripePrice->id]);
            $product->setActive(false);
            $this->productRepo->save($product, true);
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
            $checkout = $this->checkoutRepo->findOneBy(['stripeSessionId' => $session['id']]);
            $this->logger->notice('Checkout expired: ' . $checkout->getStripeSessionId());
            $checkout->setStatus(-1);
            $this->checkoutRepo->save($checkout, true);
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
            $checkout = $this->checkoutRepo->findOneBy(['stripeSessionId' => $session['id']]);
            $this->logger->notice('Checkout done: ' . $checkout->getStripeSessionId());
            $checkout->setStatus(1);
            $this->checkoutRepo->save($checkout, true);
            if ($checkout->getStatus() == 1) {
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
                        $given = $this->giveEventTicketToEmail($event, $product, $quantity, $email, $locale);
                        $tickets = [...$tickets, ...$given];
                    }
                }
                $qrGenerator = new Qr();
                foreach ($tickets as $ticket) {
                    $qrs[] = [
                        'qr' => $qrGenerator->getQr((string)$ticket->getReferenceNumber()),
                        'name' => $ticket->getName() ?? 'Ticket'
                    ];
                }
                $this->sendTicketQrEmail(
                    $event,
                    $event->getNameByLang($locale),
                    $email,
                    $qrs,
                    $event->getPicture()
                );
                $checkout->setStatus(2);
                $this->checkoutRepo->save($checkout, true);
                foreach ($products as $cartItem) {
                    $product = $cartItem->getProduct();
                    if ($product->isTicket()) {
                        $sold = $cartItem->getQuantity() > 1 ? 'Sold ' . $cartItem->getQuantity() . ' tickets.' : 'Sold 1 ticket.';
                        $this->mm->SendToMattermost('[' . $event->getName() . '] '. $sold .' Total:' . $product->getSold() .'/'.$product->getQuantity(), 'yhdistys');
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Code: ' . $e->getCode() . ' M:' . $e->getMessage());
        }
    }
    protected function sendTicketQrEmail(
        Event $event,
        string $eventName,
        string $to,
        $qrs,
        ?SonataMediaMedia $img
    ): void {
        $email = $this->emailRepo->findOneBy(['purpose' => 'ticket_qr', 'event' => $event]);
        $replyTo = 'hallitus@entropy.fi';
        $body = '';
        if ($email != null) {
            $replyTo = $email->getReplyTo() ?? 'hallitus@entropy.fi';
            $body = $email->getBody();
        }
        foreach ($qrs as $x => $qr) {
            $subject = $x > 0 ? '[ENTROPY] ' . $qr['name'] . ' (' . ($x + 1) . ')' : '[ENTROPY] ' . $qr['name'];
            $mail =  (new TemplatedEmail())
                ->from(new Address('webmaster@entropy.fi', 'Entropy ry'))
                ->to($to)
                ->replyTo($replyTo)
                ->subject($subject)
                ->addPart((new DataPart($qr['qr'], 'ticket', 'image/png', 'base64'))->asInline())
                ->htmlTemplate('emails/ticket.html.twig')
                ->context([
                    'body' => $body,
                    'qr' => $qr,
                    'links' => $email ? $email->getAddLoginLinksToFooter() : true,
                    'img' => $img,
                    'user_email' => $to
                ]);
            $this->mailer->send($mail);
        }
    }
    protected function giveEventTicketToEmail(
        Event $event,
        Product $product,
        int $quantity,
        string $email,
        string $locale
    ): array {
        $tickets = [];
        // check if is members email
        $member = $this->memberRepo->getByEmail($email);
        for ($i = 1; $i <= $quantity; $i++) {
            $ticket = new Ticket();
            $ticket->setName($product->getName($locale) ?? 'Ticket');
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
