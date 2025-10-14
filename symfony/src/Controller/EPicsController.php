<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\EPicsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class EPicsController extends AbstractController
{
    #[Route('/api/epics/random', name: 'epics_random_pic')]
    public function getRandomPic(EPicsService $epics): JsonResponse
    {
        return new JsonResponse($epics->getRandomPhoto());
    }
}
