<?php

namespace App\Controller;

use App\Helper\ePics;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class EPicsController extends AbstractController
{
    #[Route('/api/epics/random', name: 'epics_random_pic')]
    public function getRandomPic(ePics $epics): JsonResponse
    {
        return new JsonResponse($epics->getRandomPic());
    }
}
