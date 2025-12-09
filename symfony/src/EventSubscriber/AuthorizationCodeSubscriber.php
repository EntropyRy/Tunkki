<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use League\Bundle\OAuth2ServerBundle\Event\AuthorizationRequestResolveEvent;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class AuthorizationCodeSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private RequestStack $requestStack,
    ) {
    }

    public function onAuthorizationRequestResolve(AuthorizationRequestResolveEvent $event): void
    {
        $user = $event->getUser();
        \assert($user instanceof User);
        if (null != $user) {
            $scopes = array_map(
                static fn (Scope $scope): string => (string) $scope,
                $event->getScopes(),
            );
            $requiresActiveMember = [] === $scopes ? true : [] !== array_diff($scopes, ['forum']);

            if (!$requiresActiveMember || $user->getMember()->getIsActiveMember()) {
                $event->resolveAuthorization(AuthorizationRequestResolveEvent::AUTHORIZATION_APPROVED);

                return;
            }

            $event->resolveAuthorization(AuthorizationRequestResolveEvent::AUTHORIZATION_DENIED);
            $session = $this->requestStack->getSession();
            \assert($session instanceof Session);
            $session->getFlashbag()->add('warning', 'profile.only_for_active_members');

            $url = $this->urlGenerator->generate('profile.'.$user->getMember()->getLocale());
            $response = new RedirectResponse($url);
            $event->setResponse($response);
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
