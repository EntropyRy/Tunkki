<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as Controller;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;

class MattermostAuthController extends Controller
{
    public function connectAction(ClientRegistry $registry): ?RedirectResponse
    {
        return $registry
            ->getClient('mattermost')
            ->redirect();
    }
    public function connectCheckAction(): void
    {
    }
}
