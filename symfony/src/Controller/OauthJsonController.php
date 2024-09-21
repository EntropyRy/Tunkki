<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\ExpressionLanguage\Expression;
use App\Entity\User;

class OauthJsonController extends AbstractController
{
    public function me(\Symfony\Bundle\SecurityBundle\Security $security): JsonResponse
    {
        $this->denyAccessUnlessGranted(
            new Expression(
                '"ROLE_OAUTH2_WIKI" in role_names or "ROLE_OAUTH2_FORUM" in role_names'
            )
        );
        $user = $security->getUser();
        assert($user instanceof User);
        $id = $user->getAuthId();
        return new JsonResponse(
            [
                'id' => $id,
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'active_member' => $user->getMember()->getIsActiveMember()
            ]
        );
    }
}
