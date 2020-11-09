<?php
declare(strict_types=1);
namespace App\EventListener;
use Nyholm\Psr7\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Trikoder\Bundle\OAuth2Bundle\Event\AuthorizationRequestResolveEvent;
final class AuthorizationCodeListener
{
    private $urlGenerator;
    private $requestStack;
    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        RequestStack $requestStack
    ) {
        $this->urlGenerator = $urlGenerator;
        $this->requestStack = $requestStack;
    }
    public function onAuthorizationRequestResolve(AuthorizationRequestResolveEvent $event)
    {
        if (null !== $event->getUser()) {
            $event->resolveAuthorization(AuthorizationRequestResolveEvent::AUTHORIZATION_APPROVED);
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
