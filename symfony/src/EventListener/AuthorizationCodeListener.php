<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use League\Bundle\OAuth2ServerBundle\Event\AuthorizationRequestResolveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;

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
        $url = $this->urlGenerator->generate('app_login', ['returnUrl' => $this->requestStack->getMainRequest()->getUri()]);
        $event->resolveAuthorization(AuthorizationRequestResolveEvent::AUTHORIZATION_DENIED);
        if (null !== $user) {
            if ($user->getMember()->getIsActiveMember()) {
                $event->resolveAuthorization(AuthorizationRequestResolveEvent::AUTHORIZATION_APPROVED);
            } else {
                $session = $this->requestStack->getSession();
                $session->getFlashbag()->add('warning', 'profile.only_for_active_members');

                $url = $this->urlGenerator->generate('profile.' . $user->getMember()->getLocale());
            }
        }
        $response = new Response($url);
        $event->setResponse($response);
    }
    /**
     * @return array<string, mixed>
     */
    public static function getSubscribedEvents(): array
    {
        return ['league.oauth2_server.event.authorization_request_resolve' => 'onAuthorizationRequestResolve'];
    }
}
