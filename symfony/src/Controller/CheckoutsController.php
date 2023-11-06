<?php

namespace App\Controller;

use App\Entity\Checkout;
use App\Entity\Event;
use App\Helper\AppStripeClient;
use App\Repository\CheckoutRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CheckoutsController extends AbstractController
{
    #[Route(
        path: [
            'fi' => '/{year}/{slug}/kassa',
            'en' => '/{year}/{slug}/checkout',
        ],
        name: 'event_stripe_checkouts'
    )]
    public function index(
        Request $request,
        #[MapEntity(expr: 'repository.findEventBySlugAndYear(slug,year)')]
        Event $event,
        AppStripeClient $stripe,
        CheckoutRepository $cRepo,
    ): Response {
        $client = $stripe->getClient();
        $returnUrl = $stripe->getReturnUrl();
        $products = $event->getProducts();
        $session = $request->getSession();
        $quantity = $session->get('quantity');
        $email = $session->get('email');
        $expires = new \DateTime('+30min');
        $lineItems = [];
        foreach ($products as $product) {
            $lineItems[] = $product->getLineItems($quantity);
        }
        if (count($lineItems) > 0) {
            $stripeSession = $client->checkout->sessions->create([
                'ui_mode' => 'embedded',
                'line_items' => [$lineItems],
                'mode' => 'payment',
                'return_url' => $returnUrl,
                'automatic_tax' => [
                    'enabled' => true,
                ],
                'customer_email' => $email,
                'expires_at' => $expires->format('U'),
                'locale' => $request->getLocale()
            ]);
            $checkout = new Checkout();
            $checkout->setStripeSessionId($stripeSession['id']);
            $checkout->setEmail($email);
            $cRepo->add($checkout, true);
            $session->set('StripeSessionId', $session['id']);
        }
        return $this->render('event/checkouts.html.twig', [
            'stripeSession' => $session,
            'event' => $event,
            'publicKey' => $this->getParameter('stripe_public_key')
        ]);
    }
}
