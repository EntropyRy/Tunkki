<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

class OauthJsonController extends AbstractController
{
    /**
     * @IsGranted("ROLE_OAUTH2_USER.VIEW")
     */
    public function me(Request $request, Security $security): JsonResponse
    {
        $user = $security->getUser();
        return new JsonResponse(['username' => $user->getUsername(), 'email' => $user->getEmail()]);
    }
}
