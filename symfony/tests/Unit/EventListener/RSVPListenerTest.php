<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\Event;
use App\Entity\RSVP;
use App\Enum\EmailPurpose;
use App\EventListener\RSVPListener;
use App\Service\Email\EmailService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \App\EventListener\RSVPListener
 */
final class RSVPListenerTest extends TestCase
{
    private RSVPListener $listener;
    private EmailService $emailService;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->emailService = $this->createStub(EmailService::class);
        $this->logger = $this->createStub(LoggerInterface::class);

        $this->listener = new RSVPListener(
            $this->emailService,
            $this->logger,
        );
    }

    public function testSendRSVPMailListenerWithRsvpSystemDisabled(): void
    {
        // Override emailService with mock for this test
        $mockEmailService = $this->createMock(EmailService::class);
        $listener = new RSVPListener($mockEmailService, $this->logger);

        $event = $this->createStub(Event::class);
        $event->method('getRsvpSystemEnabled')->willReturn(false);

        $rsvp = $this->createStub(RSVP::class);
        $rsvp->method('getEvent')->willReturn($event);
        $rsvp->method('getAvailableEmail')->willReturn('test@example.com');

        $mockEmailService->expects($this->never())->method('sendToRecipient');

        $listener->sendRSVPMailListener($rsvp);
    }

    public function testSendRSVPMailListenerWithSendEmailDisabled(): void
    {
        // Override emailService with mock for this test
        $mockEmailService = $this->createMock(EmailService::class);
        $listener = new RSVPListener($mockEmailService, $this->logger);

        $event = $this->createStub(Event::class);
        $event->method('getRsvpSystemEnabled')->willReturn(true);
        $event->method('isSendRsvpEmail')->willReturn(false);

        $rsvp = $this->createStub(RSVP::class);
        $rsvp->method('getEvent')->willReturn($event);
        $rsvp->method('getAvailableEmail')->willReturn('test@example.com');

        $mockEmailService->expects($this->never())->method('sendToRecipient');

        $listener->sendRSVPMailListener($rsvp);
    }

    public function testSendRSVPMailListenerWithNoUserEmail(): void
    {
        // Override emailService with mock for this test
        $mockEmailService = $this->createMock(EmailService::class);
        $listener = new RSVPListener($mockEmailService, $this->logger);

        $event = $this->createStub(Event::class);
        $event->method('getRsvpSystemEnabled')->willReturn(true);
        $event->method('isSendRsvpEmail')->willReturn(true);

        $rsvp = $this->createStub(RSVP::class);
        $rsvp->method('getEvent')->willReturn($event);
        $rsvp->method('getAvailableEmail')->willReturn(''); // Empty string, not null

        $mockEmailService->expects($this->never())->method('sendToRecipient');

        $listener->sendRSVPMailListener($rsvp);
    }

    public function testSendRSVPMailListenerWithNoEmailTemplate(): void
    {
        // Override logger with mock for this test only
        $mockLogger = $this->createMock(LoggerInterface::class);
        $listener = new RSVPListener($this->emailService, $mockLogger);

        $event = $this->createStub(Event::class);
        $event->method('getRsvpSystemEnabled')->willReturn(true);
        $event->method('isSendRsvpEmail')->willReturn(true);
        $event->method('getId')->willReturn(1);

        $rsvp = $this->createStub(RSVP::class);
        $rsvp->method('getEvent')->willReturn($event);
        $rsvp->method('getAvailableEmail')->willReturn('test@example.com');
        $rsvp->method('getId')->willReturn(1);

        // EmailService will throw an exception when template not found
        $this->emailService->method('sendToRecipient')
            ->willThrowException(new \RuntimeException('Email template not found'));

        // Logger should be called with error
        $mockLogger->expects($this->once())->method('error');

        $listener->sendRSVPMailListener($rsvp);
    }

    public function testSendRSVPMailListenerSuccess(): void
    {
        // Override emailService with mock for this test
        $mockEmailService = $this->createMock(EmailService::class);
        $listener = new RSVPListener($mockEmailService, $this->logger);

        $event = $this->createStub(Event::class);
        $event->method('getRsvpSystemEnabled')->willReturn(true);
        $event->method('isSendRsvpEmail')->willReturn(true);

        $rsvp = $this->createStub(RSVP::class);
        $rsvp->method('getEvent')->willReturn($event);
        $rsvp->method('getAvailableEmail')->willReturn('user@example.com');

        $mockEmailService->expects($this->once())
            ->method('sendToRecipient')
            ->with(EmailPurpose::RSVP, 'user@example.com', $event);

        $listener->sendRSVPMailListener($rsvp);
    }

    public function testSendRSVPMailListenerWithDefaultReplyTo(): void
    {
        // Override emailService with mock for this test
        $mockEmailService = $this->createMock(EmailService::class);
        $listener = new RSVPListener($mockEmailService, $this->logger);

        // This test is no longer relevant as EmailService handles reply-to internally
        // Testing that EmailService is called is sufficient
        $event = $this->createStub(Event::class);
        $event->method('getRsvpSystemEnabled')->willReturn(true);
        $event->method('isSendRsvpEmail')->willReturn(true);

        $rsvp = $this->createStub(RSVP::class);
        $rsvp->method('getEvent')->willReturn($event);
        $rsvp->method('getAvailableEmail')->willReturn('user@example.com');

        $mockEmailService->expects($this->once())
            ->method('sendToRecipient')
            ->with(EmailPurpose::RSVP, 'user@example.com', $event);

        $listener->sendRSVPMailListener($rsvp);
    }
}
