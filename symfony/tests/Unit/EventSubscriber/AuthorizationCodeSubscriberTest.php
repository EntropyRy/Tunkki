<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\Member;
use App\Entity\User;
use App\EventSubscriber\AuthorizationCodeSubscriber;
use League\Bundle\OAuth2ServerBundle\Event\AuthorizationRequestResolveEvent;
use League\Bundle\OAuth2ServerBundle\Model\ClientInterface;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @covers \App\EventSubscriber\AuthorizationCodeSubscriber
 */
final class AuthorizationCodeSubscriberTest extends TestCase
{
    private AuthorizationCodeSubscriber $subscriber;
    private UrlGeneratorInterface $urlGenerator;
    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->requestStack = $this->createStub(RequestStack::class);

        $this->createSubscriber();
    }

    private function createSubscriber(?UrlGeneratorInterface $urlGenerator = null): void
    {
        $this->urlGenerator = $urlGenerator ?? $this->createStub(UrlGeneratorInterface::class);

        $this->subscriber = new AuthorizationCodeSubscriber(
            $this->urlGenerator,
            $this->requestStack,
        );
    }

    public function testGetSubscribedEvents(): void
    {
        $events = AuthorizationCodeSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey('league.oauth2_server.event.authorization_request_resolve', $events);
        $this->assertSame('onAuthorizationRequestResolve', $events['league.oauth2_server.event.authorization_request_resolve']);
    }

    public function testOnAuthorizationRequestResolveApprovesActiveMember(): void
    {
        $member = $this->createStub(Member::class);
        $member->method('getIsActiveMember')->willReturn(true);

        $user = $this->createStub(User::class);
        $user->method('getMember')->willReturn($member);

        $authRequest = $this->createStub(AuthorizationRequestInterface::class);
        $client = $this->createStub(ClientInterface::class);

        $event = new AuthorizationRequestResolveEvent($authRequest, [], $client, $user);

        $this->subscriber->onAuthorizationRequestResolve($event);

        $this->assertTrue($event->getAuthorizationResolution());
    }

    public function testOnAuthorizationRequestResolveDeniesInactiveMember(): void
    {
        $member = $this->createStub(Member::class);
        $member->method('getIsActiveMember')->willReturn(false);
        $member->method('getLocale')->willReturn('fi');

        $user = $this->createStub(User::class);
        $user->method('getMember')->willReturn($member);

        $session = new Session(new MockArraySessionStorage());
        $this->requestStack->method('getSession')->willReturn($session);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->with('profile.fi')
            ->willReturn('/profile');

        $this->createSubscriber($urlGenerator);

        $authRequest = $this->createStub(AuthorizationRequestInterface::class);
        $client = $this->createStub(ClientInterface::class);

        $event = new AuthorizationRequestResolveEvent($authRequest, [], $client, $user);

        $this->subscriber->onAuthorizationRequestResolve($event);

        $this->assertFalse($event->getAuthorizationResolution());
        $this->assertInstanceOf(RedirectResponse::class, $event->getResponse());
        $this->assertSame('/profile', $event->getResponse()->getTargetUrl());

        $flashes = $session->getFlashBag()->get('warning');
        $this->assertCount(1, $flashes);
        $this->assertSame('profile.only_for_active_members', $flashes[0]);
    }

    public function testOnAuthorizationRequestResolveDeniesInactiveMemberEnglishLocale(): void
    {
        $member = $this->createStub(Member::class);
        $member->method('getIsActiveMember')->willReturn(false);
        $member->method('getLocale')->willReturn('en');

        $user = $this->createStub(User::class);
        $user->method('getMember')->willReturn($member);

        $session = new Session(new MockArraySessionStorage());
        $this->requestStack->method('getSession')->willReturn($session);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->with('profile.en')
            ->willReturn('/en/profile');

        $this->createSubscriber($urlGenerator);

        $authRequest = $this->createStub(AuthorizationRequestInterface::class);
        $client = $this->createStub(ClientInterface::class);

        $event = new AuthorizationRequestResolveEvent($authRequest, [], $client, $user);

        $this->subscriber->onAuthorizationRequestResolve($event);

        $this->assertFalse($event->getAuthorizationResolution());
        $this->assertInstanceOf(RedirectResponse::class, $event->getResponse());
        $this->assertSame('/en/profile', $event->getResponse()->getTargetUrl());

        $flashes = $session->getFlashBag()->get('warning');
        $this->assertCount(1, $flashes);
        $this->assertSame('profile.only_for_active_members', $flashes[0]);
    }

    /*
     * Note: The null user path in onAuthorizationRequestResolve() is technically unreachable
     * with the current OAuth2ServerBundle implementation, as AuthorizationRequestResolveEvent::getUser()
     * returns UserInterface (not nullable). The null check exists in the code but cannot be
     * triggered in unit tests without violating type contracts. In practice, unauthenticated
     * users would be redirected to login before this event is dispatched.
     */
}
