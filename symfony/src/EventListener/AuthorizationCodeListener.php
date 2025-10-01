<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use League\Bundle\OAuth2ServerBundle\Event\AuthorizationRequestResolveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class AuthorizationCodeListener implements EventSubscriberInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private RequestStack $requestStack,
    ) {
    }

    public function onAuthorizationRequestResolve(AuthorizationRequestResolveEvent $event): void
    {
        $user = $event->getUser();
        assert($user instanceof User);
        if (null != $user) {
            if ($user->getMember()->getIsActiveMember()) {
                $event->resolveAuthorization(AuthorizationRequestResolveEvent::AUTHORIZATION_APPROVED);
            } else {
                $event->resolveAuthorization(AuthorizationRequestResolveEvent::AUTHORIZATION_DENIED);
                $session = $this->requestStack->getSession();
                assert($session instanceof Session);
                $session->getFlashbag()->add('warning', 'profile.only_for_active_members');

                $url = $this->urlGenerator->generate('profile.'.$user->getMember()->getLocale());
                $response = new RedirectResponse($url);
                $event->setResponse($response);
            }
        } else {
            $url = $this->urlGenerator->generate('app_login', ['returnUrl' => $this->requestStack->getMainRequest()->getUri()]);
            $response = new RedirectResponse($url);
            $event->setResponse($response);
        }
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return ['league.oauth2_server.event.authorization_request_resolve' => 'onAuthorizationRequestResolve'];
    }
}
