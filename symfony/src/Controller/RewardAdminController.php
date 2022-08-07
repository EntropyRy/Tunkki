<?php

declare(strict_types=1);

namespace App\Controller;

use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use App\Entity\Reward;

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
    public function prepareEvenoutAction()
    {
        $link = $this->admin->generateUrl('Evenout');
        $rewards = $this->getDoctrine()->getManager()->getRepository(Reward::class)->findBy(['paid' => false]);
        $total['pool'] = 0;
        $total['sum'] = 0;
        foreach ($rewards as $reward) {
            $total['pool'] += $reward->getReward();
            $total['sum'] += $reward->getWeight();
        }
        $data['button'] = '<a class="btn btn-primary" role="button" href="'.$link.'">EVENOUT</a>';
        $data['rewards'] = $rewards;
        $data['total'] = $total;

        return $this->render('admin/reward/prepare.html.twig', $data);
    }
    public function EvenoutAction()
    {
        $rewards = $this->getDoctrine()->getManager()->getRepository(Reward::class)->findBy(['paid' => false]);
        $total['pool'] = 0;
        $total['sum'] = 0;
        foreach ($rewards as $reward) {
            $total['pool'] += $reward->getReward();
            $total['sum'] += $reward->getWeight();
        }
        foreach ($rewards as $reward) {
            $reward->setEvenout(strval($total['pool'] * $reward->getWeight() / $total['sum']));
            $this->admin->update($reward);
        }
        $this->addFlash('sonata_flash_success', sprintf('Nee Distribution calculated!'));

        return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
    }
}
