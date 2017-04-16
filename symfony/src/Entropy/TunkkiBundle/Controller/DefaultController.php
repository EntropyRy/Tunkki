<?php

namespace Entropy\TunkkiBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction($name)
    {
        return $this->render('EntropyTunkkiBundle:Default:index.html.twig', array('name' => $name));
    }
}
