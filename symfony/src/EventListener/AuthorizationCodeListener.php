<?php

declare(strict_types=1);

namespace App\EventListener;

use Nyholm\Psr7\Response;
use App\Entity\User;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use League\Bundle\OAuth2ServerBundle\Event\AuthorizationRequestResolveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class AuthorizationCodeListener implements EventSubscriberInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private RequestStack $requestStack
    ) {
    }
    public function onAuthorizationRequestResolve(AuthorizationRequestResolveEvent $event): void
    {
        $user = $event->getUser();
        assert($user instanceof User);
        if (null !== $user) {
            if ($user->getMember()->getIsActiveMember()) {
                $event->resolveAuthorization(AuthorizationRequestResolveEvent::AUTHORIZATION_APPROVED);
            } else {
                $session = $this->requestStack->getSession();
                $session->getFlashbag()->add('warning', 'profile.only_for_active_members');

                $event->setResponse(
                    new Response(
                        302,
                        ['Location' => $this->urlGenerator->generate('profile.' . $user->getMember()->getLocale())]
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
                                'returnUrl' => $this->requestStack->getMainRequest()->getUri(),
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
