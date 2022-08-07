<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security as SA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Hashids\Hashids;

class OauthJsonController extends AbstractController
{
    /**
     * @SA("is_granted('ROLE_OAUTH2_USER.VIEW') or is_granted('ROLE_OAUTH2_FORUM')")
     */
    public function me(Request $request, Security $security): JsonResponse
    {
        $user = $security->getUser();
        $hash = new Hashids('dalt', 6);
        $id = $hash->encode($user->getId());
        return new JsonResponse([
            'id' => $id,
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'active_member' => $user->getMember()->getIsActiveMember()
        ]);
    }
}
