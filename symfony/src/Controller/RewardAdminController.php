<?php

declare(strict_types=1);

namespace App\Controller;

use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use App\Entity\Reward;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class RewardAdminController extends CRUDController
{
    public function __construct(
        private readonly TokenStorageInterface $usageTrackingTokenStorage,
        private readonly EntityManagerInterface $em
    ) {
    }
    public function makepaidAction(): RedirectResponse
    {
        $reward = $this->admin->getSubject();
        $reward->setPaid(true);
        $reward->setPaidDate(new \Datetime());
        $handler = $this->usageTrackingTokenStorage->getToken()->getUser();
        $reward->setPaymentHandledBy($handler);
        $this->admin->update($reward);
        return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
    }
    public function prepareEvenoutAction(): Response
    {
        $total = [];
        $data = [];
        $link = $this->admin->generateUrl('Evenout');
        $rewards = $this->em->getRepository(Reward::class)->findBy(['paid' => false]);
        $total['pool'] = 0;
        $total['sum'] = 0;
        foreach ($rewards as $reward) {
            $total['pool'] += $reward->getReward();
            $total['sum'] += $reward->getWeight();
        }
        $data['button'] = '<a class="btn btn-primary" role="button" href="' . $link . '">EVENOUT</a>';
        $data['rewards'] = $rewards;
        $data['total'] = $total;

        return $this->render('admin/reward/prepare.html.twig', $data);
    }
    public function EvenoutAction(): RedirectResponse
    {
        $total = [];
        $rewards = $this->em->getRepository(Reward::class)->findBy(['paid' => false]);
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
        $this->addFlash('sonata_flash_success', 'New distribution calculated!');

        return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
    }
}
