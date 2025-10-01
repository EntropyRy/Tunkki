<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;

class MattermostAuthController extends Controller
{
    public function connectAction(ClientRegistry $registry): ?RedirectResponse
    {
        return $registry
            ->getClient('mattermost')
            ->redirect([], []);
    }

    public function connectCheckAction(): void
    {
    }
}
