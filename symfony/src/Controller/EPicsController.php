<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Helper\ePics;

class EPicsController extends AbstractController
{
    public function getRandomPic(ePics $epics): JsonResponse
    {
        return new JsonResponse($epics->getRandomPic());
    }
}
