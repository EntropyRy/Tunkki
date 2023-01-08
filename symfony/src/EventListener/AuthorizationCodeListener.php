<?php

declare(strict_types=1);

namespace App\EventListener;

use Nyholm\Psr7\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use League\Bundle\OAuth2ServerBundle\Event\AuthorizationRequestResolveEvent;

final readonly class AuthorizationCodeListener implements \Symfony\Component\EventDispatcher\EventSubscriberInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private RequestStack $requestStack
    ) {
    }
    public function onAuthorizationRequestResolve(AuthorizationRequestResolveEvent $event): void
    {
        if (null !== $event->getUser()) {
            if ($event->getUser()->getMember()->getIsActiveMember()) {
                $event->resolveAuthorization(AuthorizationRequestResolveEvent::AUTHORIZATION_APPROVED);
            } else {
                $this->requestStack->getSession()->getFlashbag()->add('warning', 'profile.only_for_active_members');

                $event->setResponse(
                    new Response(
                        302,
                        ['Location' => $this->urlGenerator->generate('profile.' . $event->getUser()->getMember()->getLocale())]
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
    /**
     * @return array<string, mixed>
     */
    public static function getSubscribedEvents(): array
    {
        return ['league.oauth2_server.event.authorization_request_resolve' => 'onAuthorizationRequestResolve'];
    }
}
