<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\Email;
use App\Entity\Event;
use App\Entity\RSVP;
use App\EventListener\RSVPListener;
use App\Repository\EmailRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;

/**
 * @covers \App\EventListener\RSVPListener
 */
final class RSVPListenerTest extends TestCase
{
    private RSVPListener $listener;
    private MailerInterface $mailer;
    private EmailRepository $emailRepository;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->emailRepository = $this->createMock(EmailRepository::class);

        $this->listener = new RSVPListener(
            $this->mailer,
            $this->emailRepository,
        );
    }

    public function testSendRSVPMailListenerWithRsvpSystemDisabled(): void
    {
        $event = $this->createMock(Event::class);
        $event->method('getRsvpSystemEnabled')->willReturn(false);

        $rsvp = $this->createMock(RSVP::class);
        $rsvp->method('getEvent')->willReturn($event);
        $rsvp->method('getAvailableEmail')->willReturn('test@example.com');

        $this->mailer->expects($this->never())->method('send');

        $this->listener->sendRSVPMailListener($rsvp);
    }

    public function testSendRSVPMailListenerWithSendEmailDisabled(): void
    {
        $event = $this->createMock(Event::class);
        $event->method('getRsvpSystemEnabled')->willReturn(true);
        $event->method('isSendRsvpEmail')->willReturn(false);

        $rsvp = $this->createMock(RSVP::class);
        $rsvp->method('getEvent')->willReturn($event);
        $rsvp->method('getAvailableEmail')->willReturn('test@example.com');

        $this->mailer->expects($this->never())->method('send');

        $this->listener->sendRSVPMailListener($rsvp);
    }

    public function testSendRSVPMailListenerWithNoUserEmail(): void
    {
        $event = $this->createMock(Event::class);
        $event->method('getRsvpSystemEnabled')->willReturn(true);
        $event->method('isSendRsvpEmail')->willReturn(true);

        $rsvp = $this->createMock(RSVP::class);
        $rsvp->method('getEvent')->willReturn($event);
        $rsvp->method('getAvailableEmail')->willReturn(''); // Empty string, not null

        $this->mailer->expects($this->never())->method('send');

        $this->listener->sendRSVPMailListener($rsvp);
    }

    public function testSendRSVPMailListenerWithNoEmailTemplate(): void
    {
        $event = $this->createMock(Event::class);
        $event->method('getRsvpSystemEnabled')->willReturn(true);
        $event->method('isSendRsvpEmail')->willReturn(true);

        $rsvp = $this->createMock(RSVP::class);
        $rsvp->method('getEvent')->willReturn($event);
        $rsvp->method('getAvailableEmail')->willReturn('test@example.com');

        $this->emailRepository->method('findOneBy')->willReturn(null);

        $this->mailer->expects($this->never())->method('send');

        $this->listener->sendRSVPMailListener($rsvp);
    }

    public function testSendRSVPMailListenerSuccess(): void
    {
        $event = $this->createMock(Event::class);
        $event->method('getRsvpSystemEnabled')->willReturn(true);
        $event->method('isSendRsvpEmail')->willReturn(true);
        $event->method('getPicture')->willReturn(null);

        $rsvp = $this->createMock(RSVP::class);
        $rsvp->method('getEvent')->willReturn($event);
        $rsvp->method('getAvailableEmail')->willReturn('user@example.com');

        $emailTemplate = $this->createMock(Email::class);
        $emailTemplate->method('getReplyTo')->willReturn('organizer@example.com');
        $emailTemplate->method('getSubject')->willReturn('RSVP Confirmation');
        $emailTemplate->method('getBody')->willReturn('Thank you for your RSVP!');
        $emailTemplate->method('getAddLoginLinksToFooter')->willReturn(true);

        $this->emailRepository->method('findOneBy')->with([
            'event' => $event,
            'purpose' => 'rsvp',
        ])->willReturn($emailTemplate);

        $this->mailer->expects($this->once())->method('send');

        $this->listener->sendRSVPMailListener($rsvp);
    }

    public function testSendRSVPMailListenerWithDefaultReplyTo(): void
    {
        $event = $this->createMock(Event::class);
        $event->method('getRsvpSystemEnabled')->willReturn(true);
        $event->method('isSendRsvpEmail')->willReturn(true);
        $event->method('getPicture')->willReturn(null);

        $rsvp = $this->createMock(RSVP::class);
        $rsvp->method('getEvent')->willReturn($event);
        $rsvp->method('getAvailableEmail')->willReturn('user@example.com');

        $emailTemplate = $this->createMock(Email::class);
        $emailTemplate->method('getReplyTo')->willReturn(null); // No custom reply-to
        $emailTemplate->method('getSubject')->willReturn('RSVP Confirmation');
        $emailTemplate->method('getBody')->willReturn('Thank you!');
        $emailTemplate->method('getAddLoginLinksToFooter')->willReturn(false);

        $this->emailRepository->method('findOneBy')->willReturn($emailTemplate);

        $this->mailer->expects($this->once())->method('send');

        $this->listener->sendRSVPMailListener($rsvp);
    }
}
