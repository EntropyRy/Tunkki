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
    private $urlGenerator;
    private $requestStack;
    private $session;

    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        RequestStack $requestStack,
        SessionInterface $session
    ) {
        $this->urlGenerator = $urlGenerator;
        $this->requestStack = $requestStack;
        $this->session      = $session;
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
