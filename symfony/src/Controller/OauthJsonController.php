<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;

class OauthJsonController extends AbstractController
{
    public function me(Security $security): JsonResponse
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
                'active_member' => $user->getMember()->getIsActiveMember(),
            ]
        );
    }
}
