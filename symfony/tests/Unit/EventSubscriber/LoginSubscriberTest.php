<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\Member;
use App\Entity\User;
use App\EventSubscriber\LoginSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Translation\LocaleSwitcher;

/**
 * @covers \App\EventSubscriber\LoginSubscriber
 */
final class LoginSubscriberTest extends TestCase
{
    private LoginSubscriber $subscriber;
    private EntityManagerInterface $em;
    private LocaleSwitcher $localeSwitcher;
    private UrlGeneratorInterface $urlGenerator;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->localeSwitcher = $this->createMock(LocaleSwitcher::class);
        $this->urlGenerator = $this->createStub(UrlGeneratorInterface::class);

        $this->subscriber = new LoginSubscriber(
            $this->localeSwitcher,
            $this->em,
            $this->urlGenerator,
        );
    }

    public function testGetSubscribedEvents(): void
    {
        $events = LoginSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(LoginSuccessEvent::class, $events);
        $this->assertSame('onLoginSuccess', $events[LoginSuccessEvent::class]);
    }

    public function testOnLoginSuccessUpdatesLastLogin(): void
    {
        $member = $this->createStub(Member::class);
        $member->method('getLocale')->willReturn('fi');
        $member->method('isEmailVerified')->willReturn(true);

        $user = $this->createStub(User::class);
        $user->method('getMember')->willReturn($member);

        $event = $this->createStub(LoginSuccessEvent::class);
        $event->method('getUser')->willReturn($user);
        $event->method('getRequest')->willReturn(new Request());

        $this->em->expects($this->once())->method('persist')->with($user);
        $this->em->expects($this->once())->method('flush');

        $this->subscriber->onLoginSuccess($event);
    }

    public function testOnLoginSuccessSwitchesLocale(): void
    {
        $member = $this->createStub(Member::class);
        $member->method('getLocale')->willReturn('en');
        $member->method('isEmailVerified')->willReturn(true);

        $user = $this->createStub(User::class);
        $user->method('getMember')->willReturn($member);

        $event = $this->createStub(LoginSuccessEvent::class);
        $event->method('getUser')->willReturn($user);
        $event->method('getRequest')->willReturn(new Request());

        $this->localeSwitcher
            ->expects($this->once())
            ->method('setLocale')
            ->with('en');

        $this->subscriber->onLoginSuccess($event);
    }

    public function testOnLoginSuccessAddsFlashForUnverifiedEmailFinnish(): void
    {
        $member = $this->createStub(Member::class);
        $member->method('getLocale')->willReturn('fi');
        $member->method('isEmailVerified')->willReturn(false);

        $user = $this->createStub(User::class);
        $user->method('getMember')->willReturn($member);

        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);

        $event = $this->createStub(LoginSuccessEvent::class);
        $event->method('getUser')->willReturn($user);
        $event->method('getRequest')->willReturn($request);

        $this->urlGenerator
            ->method('generate')
            ->with('profile_resend_verification', ['_locale' => 'fi'])
            ->willReturn('/fi/profile/resend-verification');

        $this->subscriber->onLoginSuccess($event);

        $flashes = $session->getFlashBag()->get('warning_html');
        $this->assertCount(1, $flashes);
        $this->assertStringContainsString('Sähköpostiosoitteesi ei ole vahvistettu', $flashes[0]);
        $this->assertStringContainsString('Lähetä vahvistussähköposti uudelleen', $flashes[0]);
        $this->assertStringContainsString('/fi/profile/resend-verification', $flashes[0]);
    }

    public function testOnLoginSuccessAddsFlashForUnverifiedEmailEnglish(): void
    {
        $member = $this->createStub(Member::class);
        $member->method('getLocale')->willReturn('en');
        $member->method('isEmailVerified')->willReturn(false);

        $user = $this->createStub(User::class);
        $user->method('getMember')->willReturn($member);

        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);

        $event = $this->createStub(LoginSuccessEvent::class);
        $event->method('getUser')->willReturn($user);
        $event->method('getRequest')->willReturn($request);

        $this->urlGenerator
            ->method('generate')
            ->with('profile_resend_verification', ['_locale' => 'en'])
            ->willReturn('/en/profile/resend-verification');

        $this->subscriber->onLoginSuccess($event);

        $flashes = $session->getFlashBag()->get('warning_html');
        $this->assertCount(1, $flashes);
        $this->assertStringContainsString('Your email address is not verified', $flashes[0]);
        $this->assertStringContainsString('Resend verification email', $flashes[0]);
        $this->assertStringContainsString('/en/profile/resend-verification', $flashes[0]);
    }

    public function testOnLoginSuccessNoFlashWhenEmailVerified(): void
    {
        $member = $this->createStub(Member::class);
        $member->method('getLocale')->willReturn('fi');
        $member->method('isEmailVerified')->willReturn(true);

        $user = $this->createStub(User::class);
        $user->method('getMember')->willReturn($member);

        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);

        $event = $this->createStub(LoginSuccessEvent::class);
        $event->method('getUser')->willReturn($user);
        $event->method('getRequest')->willReturn($request);

        $this->subscriber->onLoginSuccess($event);

        $flashes = $session->getFlashBag()->get('warning_html');
        $this->assertCount(0, $flashes);
    }

    public function testOnLoginSuccessHandlesRequestWithoutSession(): void
    {
        $member = $this->createStub(Member::class);
        $member->method('getLocale')->willReturn('fi');
        $member->method('isEmailVerified')->willReturn(false);

        $user = $this->createStub(User::class);
        $user->method('getMember')->willReturn($member);

        $request = new Request();
        // No session set

        $event = $this->createStub(LoginSuccessEvent::class);
        $event->method('getUser')->willReturn($user);
        $event->method('getRequest')->willReturn($request);

        // Should not throw exception
        $this->subscriber->onLoginSuccess($event);
        $this->assertTrue(true); // Test passes if no exception thrown
    }
}
