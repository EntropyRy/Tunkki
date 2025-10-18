<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\User;
use App\Form\CartType;
use App\Repository\CartRepository;
use App\Repository\CheckoutRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * ShopController - Handles the general store (non-event products).
 */
class ShopController extends AbstractController
{
    use TargetPathTrait;

    #[
        Route(
            path: [
                'fi' => '/kauppa',
                'en' => '/shop',
            ],
            name: 'entropy_shop',
        ),
    ]
    public function shop(
        Request $request,
        ProductRepository $productRepo,
        CartRepository $cartR,
        CheckoutRepository $checkoutR,
    ): Response {
        // General store: products NOT linked to events
        $user = $this->getUser();
        $email = null;

        if (null != $user) {
            \assert($user instanceof User);
            $email = $user->getEmail();
        }

        $session = $request->getSession();
        $cart = new Cart();
        $cartId = $session->get('cart');
        if (null != $cartId) {
            $cart = $cartR->findOneBy(['id' => $cartId]);
            if (null == $cart) {
                $cart = new Cart();
            }
        }
        if (null == $cart->getEmail()) {
            $cart->setEmail($email);
        }

        // Fetch general store products (not linked to events)
        $products = $productRepo->findGeneralStoreProducts();
        $max = $checkoutR->findProductQuantitiesInOngoingCheckouts();

        $cart->setProducts($products);
        $form = $this->createForm(CartType::class, $cart);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $cart = $form->getData();
            $cartR->save($cart, true);
            $session->set('cart', $cart->getId());

            return $this->redirectToRoute('stripe_checkout');
        }

        // Save target path for login redirect
        $this->saveTargetPath($session, 'main', $request->getUri());

        return $this->render('shop/index.html.twig', [
            'form' => $form,
            'inCheckouts' => $max,
        ]);
    }
}
