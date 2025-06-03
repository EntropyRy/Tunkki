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
        $data = $epics->getRandomPic();
        $response = new JsonResponse($data);
        
        // Set headers to prevent Cloudflare from caching this API response
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        $response->headers->set('CF-Cache-Status', 'BYPASS');
        
        // Always allow browser to cache briefly to reduce load
        $response->setMaxAge(5);
        
        return $response;
    }
}
