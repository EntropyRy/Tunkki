<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\EventVolunteerController;
use App\Entity\Event;
use App\Entity\Member;
use App\Entity\RSVP;
use App\Entity\User;
use App\Repository\RSVPRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Testable subclass that overrides dependencies that require full Symfony setup.
 */
class TestableEventVolunteerController extends EventVolunteerController
{
    private User $testUser;
    private RouterInterface $testRouter;
    private FlashBagInterface $testFlashBag;

    public function setTestDependencies(
        User $user,
        RouterInterface $router,
        FlashBagInterface $flashBag,
    ): void {
        $this->testUser = $user;
        $this->testRouter = $router;
        $this->testFlashBag = $flashBag;
    }

    #[\Override]
    protected function getUser(): User
    {
        return $this->testUser;
    }

    #[\Override]
    protected function addFlash(string $type, mixed $message): void
    {
        $this->testFlashBag->add($type, $message);
    }

    #[\Override]
    protected function redirectToRoute(
        string $route,
        array $parameters = [],
        int $status = 302,
    ): RedirectResponse {
        $url = $this->testRouter->generate($route, $parameters);

        return new RedirectResponse($url, $status);
    }
}

/**
 * Unit test for EventVolunteerController::rsvp() method.
 *
 * This test specifically covers the UniqueConstraintViolationException handling
 * (lines 294-295) which is a race condition edge case that cannot be easily
 * triggered in functional tests.
 */
#[CoversClass(EventVolunteerController::class)]
final class EventVolunteerControllerRsvpTest extends TestCase
{
    private TestableEventVolunteerController $controller;
    private EntityManagerInterface $em;
    private RSVPRepository $rsvpRepository;
    private TranslatorInterface $translator;
    private RouterInterface $router;
    private FlashBagInterface $flashBag;
    private User $user;

    protected function setUp(): void
    {
        $this->em = $this->createStub(EntityManagerInterface::class);
        $this->rsvpRepository = $this->createStub(RSVPRepository::class);
        $this->translator = $this->createStub(TranslatorInterface::class);
        $this->router = $this->createStub(RouterInterface::class);
        $this->flashBag = $this->createMock(FlashBagInterface::class);

        // Create user mock
        $member = $this->createStub(Member::class);
        $member->method('getId')->willReturn(1);
        $this->user = $this->createStub(User::class);
        $this->user->method('getMember')->willReturn($member);

        $this->controller = new TestableEventVolunteerController();
        $this->controller->setTestDependencies($this->user, $this->router, $this->flashBag);
    }

    /**
     * Test that UniqueConstraintViolationException is caught and handled gracefully.
     * Covers lines 294-295.
     */
    public function testRsvpCatchesUniqueConstraintViolationException(): void
    {
        $member = $this->user->getMember();

        $event = $this->createStub(Event::class);
        $event->method('getRsvpSystemEnabled')->willReturn(true);
        $event->method('getUrl')->willReturn('test-event');
        $event->method('getId')->willReturn(1);

        // Repository check passes (no existing RSVP found - simulating race condition)
        $this->rsvpRepository->method('existsForMemberAndEvent')
            ->with($member, $event)
            ->willReturn(false);

        // Translator returns the message key as-is
        $this->translator->method('trans')->willReturnArgument(0);

        // Router generates URL
        $this->router->method('generate')
            ->with('entropy_event_slug', ['slug' => 'test-event', 'year' => 2025])
            ->willReturn('/2025/test-event');

        // EntityManager throws UniqueConstraintViolationException on flush
        // This simulates the race condition where another request created the RSVP
        $this->em->method('persist')->willReturnCallback(static function (RSVP $rsvp): void {
            // Just accept the persist
        });

        $exception = $this->createStub(UniqueConstraintViolationException::class);
        $this->em->method('flush')->willThrowException($exception);

        // Flash bag should receive warning message for duplicate RSVP
        $this->flashBag->expects($this->once())
            ->method('add')
            ->with('warning', 'rsvp.already_rsvpd');

        // Create request
        $request = new Request(
            [],
            [],
            ['year' => 2025, 'slug' => 'test-event'],
        );

        // Execute the controller action
        $response = $this->controller->rsvp(
            $request,
            $event,
            $this->rsvpRepository,
            $this->translator,
            $this->em,
        );

        // Verify redirect response
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/2025/test-event', $response->headers->get('Location'));
    }
}
