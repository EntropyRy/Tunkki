<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\MattermostAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class MattermostAuthenticatorTest extends TestCase
{
    public function testSupportsReturnsTrueForCheckRoute(): void
    {
        $auth = $this->makeAuthenticator();

        $req = new Request();
        $req->attributes->set('_route', '_entropy_mattermost_check');

        self::assertTrue($auth->supports($req));
    }

    public function testSupportsReturnsFalseForOtherRoute(): void
    {
        $auth = $this->makeAuthenticator();

        $req = new Request();
        $req->attributes->set('_route', 'app_login');

        self::assertFalse($auth->supports($req));
    }

    public function testOnAuthenticationFailureAddsFlashAndRedirectsToLogin(): void
    {
        $urlG = $this->createMock(UrlGeneratorInterface::class);
        $urlG
            ->expects(self::once())
            ->method('generate')
            ->with('app_login')
            ->willReturn('/login');

        $auth = $this->makeAuthenticator($urlG);

        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/oauth/mm/check');
        $request->setSession($session);

        $exception = new AuthenticationException('login.join_us_plain');

        $resp = $auth->onAuthenticationFailure($request, $exception);
        self::assertInstanceOf(RedirectResponse::class, $resp);
        /* @var RedirectResponse $resp */
        self::assertSame('/login', $resp->getTargetUrl());

        $flashes = $session->getFlashBag()->peek('warning');
        self::assertNotEmpty(
            $flashes,
            'Failure should push a warning flash message',
        );
        self::assertSame('login.join_us_plain', $flashes[0]);
        self::assertTrue(
            $session->get('auth.mm.failure', false),
            'Failure flag must be set in the session',
        );
        self::assertNull(
            $session->get('_security.last_error'),
            'Last error should be cleared',
        );
    }

    public function testOnAuthenticationSuccessWithTargetPathRedirects(): void
    {
        $auth = $this->makeAuthenticator();

        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/oauth/mm/check');
        $request->setSession($session);

        // Simulate TargetPathTrait storage (_security.{firewallName}.target_path)
        $session->set('_security.main.target_path', '/account/overview');

        $response = $auth->onAuthenticationSuccess(
            $request,
            $this->createMock(TokenInterface::class),
            'main',
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        /* @var RedirectResponse $response */
        self::assertSame('/account/overview', $response->getTargetUrl());
    }

    public function testStartRedirectsToLoginWith307(): void
    {
        $auth = $this->makeAuthenticator();

        $resp = $auth->start(Request::create('/secure'));
        self::assertInstanceOf(RedirectResponse::class, $resp);
        /* @var RedirectResponse $resp */
        self::assertSame('/login/', $resp->getTargetUrl());
        self::assertSame(
            Response::HTTP_TEMPORARY_REDIRECT,
            $resp->getStatusCode(),
        );
    }

    private function makeAuthenticator(
        ?UrlGeneratorInterface $urlG = null,
    ): MattermostAuthenticator {
        $clientRegistry = $this->createMock(ClientRegistry::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $urlG = $urlG ?? $this->createMock(UrlGeneratorInterface::class);
        if ($urlG instanceof UrlGeneratorInterface) {
            // Provide a default for other calls if any (not expected in these tests)
            $urlG->method('generate')->willReturn('/login');
        }

        return new MattermostAuthenticator($clientRegistry, $em, $urlG);
    }
}
