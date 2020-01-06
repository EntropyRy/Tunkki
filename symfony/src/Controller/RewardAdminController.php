<?php

declare(strict_types=1);

namespace App\Controller;

use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class RewardAdminController extends CRUDController
{
    public function makepaidAction()
    {
        $reward = $this->admin->getSubject();
        $reward->setPaid(true);
        $reward->setPaidDate(new \Datetime());
        $handler = $this->get('security.token_storage')->getToken()->getUser();
        $reward->setPaymentHandledBy($handler);
        $this->admin->update($reward);
        return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
    }
}
