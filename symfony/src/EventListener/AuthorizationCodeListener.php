<?php

declare(strict_types=1);

namespace App\EventListener;

use Nyholm\Psr7\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Trikoder\Bundle\OAuth2Bundle\Event\AuthorizationRequestResolveEvent;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class AuthorizationCodeListener
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator, private readonly RequestStack $requestStack, private readonly SessionInterface $session)
    {
    }
    public function onAuthorizationRequestResolve(AuthorizationRequestResolveEvent $event)
    {
        if (null !== $event->getUser()) {
            if ($event->getUser()->getMember()->getIsActiveMember()) {
                $event->resolveAuthorization(AuthorizationRequestResolveEvent::AUTHORIZATION_APPROVED);
            } else {
                $this->session->getFlashbag()->add('warning', 'profile.only_for_active_members');

                $event->setResponse(
                    new Response(
                        302,
                        ['Location' => $this->urlGenerator->generate('entropy_profile.'. $event->getUser()->getMember()->getLocale())]
                    )
                );
            }
        } else {
            $event->setResponse(
                new Response(
                    302,
                    [
                        'Location' => $this->urlGenerator->generate(
                            'app_login',
                            [
                                'returnUrl' => $this->requestStack->getMasterRequest()->getUri(),
                            ]
                        ),
                    ]
                )
            );
        }
    }
}
