<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Product;
use App\Helper\AppStripeClient;
use App\Repository\ProductRepository;
use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * @extends CRUDController<Product>
 */
final class ProductAdminController extends CRUDController
{
    public function __construct(
        private readonly ProductRepository $pRepo,
    ) {
    }

    public function fetchFromStripeAction(AppStripeClient $stripe): RedirectResponse
    {
        $client = $stripe->getClient();
        $stripePrices = $client->prices->all();
        $added = 0;
        $updated = 0;
        foreach ($stripePrices as $stripePrice) {
            $product = $this->pRepo->findOneBy(['stripePriceId' => $stripePrice['id']]);
            if ($product instanceof Product) {
                ++$updated;
            } else {
                $product = new Product();
                ++$added;
            }
            $stripeProduct = $client->products->retrieve($stripePrice['product']);
            $product = $stripe->updateOurProduct($product, $stripePrice, $stripeProduct);
            $this->admin->update($product);
        }
        $this->addFlash('success', 'Updated: '.$updated.', Added: '.$added);

        return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
    }
}
