<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Helper\ePics;

class EPicsController extends AbstractController
{
    #[Route('/api/epics/random', name: 'epics_random_pic')]
    public function getRandomPic(Request $request, ePics $epics): JsonResponse
    {
        // Always get a fresh image when requested with cache buster
        $data = $epics->getRandomPic();
        $response = new JsonResponse($data);
        
        // If there's a cache buster parameter, don't cache the response
        if ($request->query->has('_')) {
            $response->setPrivate();
            $response->setMaxAge(0);
            $response->headers->addCacheControlDirective('no-store', true);
        } else {
            // Otherwise allow caching for a short period
            $response->setPublic();
            $response->setMaxAge(30); // 30 seconds cache
        }
        
        return $response;
    }
}
