<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StripeController extends AbstractController
{
    #[Route('/stripe/complete', name: 'stripe_complete')]
    public function complete(): Response
    {
        return $this->render('stripe/complete.html.twig', [
            'controller_name' => 'StripeController',
        ]);
    }
}
