<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

class OauthJsonController extends AbstractController
{
    public function me(Security $security): JsonResponse
    {
        $user = $security->getUser();
        return new JsonResponse(['username' => $user->getUsername(), 'email' => $user->getEmail()]);
    }
}
