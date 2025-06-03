<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Helper\ePics;

class EPicsController extends AbstractController
{
    #[Route('/api/epics/random', name: 'epics_random_pic')]
    public function getRandomPic(ePics $epics): JsonResponse
    {
        $response = new JsonResponse($epics->getRandomPic());
        $response->setPublic();
        $response->setMaxAge(300); // 5 minutes cache
        $response->setSharedMaxAge(3600); // 1 hour for shared caches
        $response->headers->addCacheControlDirective('must-revalidate', true);
        
        return $response;
    }
}
