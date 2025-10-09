<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Checkout;
use App\Repository\CheckoutRepository;
use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * @extends CRUDController<Checkout>
 */
final class CheckoutAdminController extends CRUDController
{
    public function __construct(
        private readonly CheckoutRepository $cRepo,
    ) {
    }

    public function removeUnneededAction(): RedirectResponse
    {
        $removed = 0;
        while ($checkouts = $this->cRepo->findUnneededCheckouts()) {
            foreach ($checkouts as $checkout) {
                $this->cRepo->remove($checkout, true);
            }
            $removed += \count($checkouts);
        }

        $this->addFlash('success', 'Removed: '.$removed);

        return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
    }
}
