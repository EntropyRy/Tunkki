<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Email;

use App\DTO\EmailRecipient;
use App\Entity\Email;
use App\Entity\Event;
use App\Entity\Member;
use App\Enum\EmailPurpose;
use App\Repository\EmailRepository;
use App\Service\Email\EmailService;
use App\Service\Email\RecipientResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;

/**
 * @covers \App\Service\Email\EmailService
 */
final class EmailServiceTest extends TestCase
{
    private EmailService $service;
    private EmailRepository $emailRepo;
    private RecipientResolver $resolver;
    private MailerInterface $mailer;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->emailRepo = $this->createStub(EmailRepository::class);
        $this->resolver = $this->createStub(RecipientResolver::class);
        $this->mailer = $this->createStub(MailerInterface::class);
        $this->em = $this->createStub(EntityManagerInterface::class);
        $clock = $this->createStub(\App\Time\ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2025-01-01 12:00:00'));

        $this->service = new EmailService(
            $this->emailRepo,
            $this->resolver,
            $this->mailer,
            $this->em,
            $clock
        );
    }

    public function testSendThrowsExceptionWhenEmailHasNoPurpose(): void
    {
        $email = $this->createStub(Email::class);
        $email->method('getPurpose')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Email entity must have a purpose set');

        $this->service->send($email);
    }

    public function testSendResolvesRecipientsFromPurposeOnly(): void
    {
        // Override mailer with mock for this test
        $mockMailer = $this->createMock(MailerInterface::class);
        $clock = $this->createStub(\App\Time\ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2025-01-01 12:00:00'));
        $service = new EmailService($this->emailRepo, $this->resolver, $mockMailer, $this->em, $clock);

        $email = $this->createStub(Email::class);
        $email->method('getPurpose')->willReturn(EmailPurpose::AKTIIVIT);
        $email->method('getRecipientGroups')->willReturn([]);
        $email->method('getEvent')->willReturn(null);
        $email->method('getSubject')->willReturn('Test Subject');
        $email->method('getBody')->willReturn('<p>Test body</p>');
        $email->method('getReplyTo')->willReturn('test@example.com');
        $email->method('getAddLoginLinksToFooter')->willReturn(true);

        $recipients = [
            new EmailRecipient('user1@example.com'),
            new EmailRecipient('user2@example.com'),
        ];

        $this->resolver->method('resolve')->willReturn($recipients);

        // Mailer should be called twice (once per recipient)
        $mockMailer->expects($this->exactly(2))->method('send');

        $result = $service->send($email);

        $this->assertSame(2, $result->totalSent);
        $this->assertSame(2, $result->totalRecipients);
        $this->assertSame([EmailPurpose::AKTIIVIT], $result->purposes);
    }

    public function testSendResolvesRecipientsFromPurposeAndGroups(): void
    {
        // Override mailer with mock for this test
        $mockMailer = $this->createMock(MailerInterface::class);
        $clock = $this->createStub(\App\Time\ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2025-01-01 12:00:00'));
        $service = new EmailService($this->emailRepo, $this->resolver, $mockMailer, $this->em, $clock);

        $email = $this->createStub(Email::class);
        $email->method('getPurpose')->willReturn(EmailPurpose::RSVP);
        $email->method('getRecipientGroups')->willReturn([EmailPurpose::TICKET, EmailPurpose::ARTIST]);
        $email->method('getEvent')->willReturn($this->createStub(Event::class));
        $email->method('getSubject')->willReturn('Test Subject');
        $email->method('getBody')->willReturn('<p>Test body</p>');
        $email->method('getReplyTo')->willReturn('test@example.com');
        $email->method('getAddLoginLinksToFooter')->willReturn(true);

        $recipients = [
            new EmailRecipient('user1@example.com'),
            new EmailRecipient('user2@example.com'),
            new EmailRecipient('user3@example.com'),
        ];

        $this->resolver->method('resolveMultiple')->willReturn($recipients);

        $mockMailer->expects($this->exactly(3))->method('send');

        $result = $service->send($email);

        $this->assertSame(3, $result->totalSent);
        $this->assertSame(3, $result->totalRecipients);
        $this->assertSame([EmailPurpose::RSVP, EmailPurpose::TICKET, EmailPurpose::ARTIST], $result->purposes);
    }

    public function testSendUpdatesEmailEntityWhenSentByProvided(): void
    {
        // Override EM and mailer with mocks for this test
        $mockEm = $this->createMock(EntityManagerInterface::class);
        $mockMailer = $this->createStub(MailerInterface::class);
        $clock = $this->createStub(\App\Time\ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2025-01-01 12:00:00'));
        $service = new EmailService($this->emailRepo, $this->resolver, $mockMailer, $mockEm, $clock);

        $email = $this->createMock(Email::class);
        $email->method('getPurpose')->willReturn(EmailPurpose::TIEDOTUS);
        $email->method('getRecipientGroups')->willReturn([]);
        $email->method('getEvent')->willReturn(null);
        $email->method('getSubject')->willReturn('Test');
        $email->method('getBody')->willReturn('<p>Test</p>');
        $email->method('getReplyTo')->willReturn('test@example.com');
        $email->method('getAddLoginLinksToFooter')->willReturn(true);

        $this->resolver->method('resolve')->willReturn([new EmailRecipient('user@example.com')]);

        $sentBy = $this->createStub(Member::class);

        // Email entity should be updated
        $email->expects($this->once())->method('setSentAt')->with($this->isInstanceOf(\DateTimeImmutable::class));
        $email->expects($this->once())->method('setSentBy')->with($sentBy);

        // EntityManager should flush
        $mockEm->expects($this->once())->method('flush');

        $service->send($email, null, $sentBy);
    }

    public function testSendToRecipientThrowsExceptionWhenTemplateNotFound(): void
    {
        $this->emailRepo->method('findOneBy')->willReturn(null);

        $event = $this->createStub(Event::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Email template not found for purpose "rsvp"');

        $this->service->sendToRecipient(
            EmailPurpose::RSVP,
            'recipient@example.com',
            $event,
            'en'
        );
    }

    public function testSendToRecipientLoadsTemplateAndSends(): void
    {
        // Override mailer with mock for this test
        $mockMailer = $this->createMock(MailerInterface::class);
        $clock = $this->createStub(\App\Time\ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2025-01-01 12:00:00'));
        $service = new EmailService($this->emailRepo, $this->resolver, $mockMailer, $this->em, $clock);

        $event = $this->createStub(Event::class);
        $event->method('getPicture')->willReturn(null);

        $emailTemplate = $this->createStub(Email::class);
        $emailTemplate->method('getSubject')->willReturn('Test Subject');
        $emailTemplate->method('getBody')->willReturn('<p>Test body</p>');
        $emailTemplate->method('getReplyTo')->willReturn('reply@example.com');
        $emailTemplate->method('getAddLoginLinksToFooter')->willReturn(false);
        $emailTemplate->method('getEvent')->willReturn($event);

        $this->emailRepo->method('findOneBy')->willReturn($emailTemplate);

        $mockMailer->expects($this->once())->method('send');

        $service->sendToRecipient(
            EmailPurpose::RSVP,
            'recipient@example.com',
            $event,
            'en'
        );
    }

    public function testSendTracksFailedRecipients(): void
    {
        // Override mailer with mock for this test
        $mockMailer = $this->createMock(MailerInterface::class);
        $clock = $this->createStub(\App\Time\ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2025-01-01 12:00:00'));
        $service = new EmailService($this->emailRepo, $this->resolver, $mockMailer, $this->em, $clock);

        $email = $this->createStub(Email::class);
        $email->method('getPurpose')->willReturn(EmailPurpose::AKTIIVIT);
        $email->method('getRecipientGroups')->willReturn([]);
        $email->method('getEvent')->willReturn(null);
        $email->method('getSubject')->willReturn('Test');
        $email->method('getBody')->willReturn('<p>Test</p>');
        $email->method('getReplyTo')->willReturn('test@example.com');
        $email->method('getAddLoginLinksToFooter')->willReturn(true);

        $recipients = [
            new EmailRecipient('success@example.com'),
            new EmailRecipient('fail@example.com'),
            new EmailRecipient('success2@example.com'),
        ];

        $this->resolver->method('resolve')->willReturn($recipients);

        // Second email fails
        $mockMailer->expects($this->exactly(3))
            ->method('send')
            ->willReturnCallback(static function ($message) {
                static $callCount = 0;
                ++$callCount;
                if (2 === $callCount) {
                    throw new \Exception('Send failed');
                }
            });

        $result = $service->send($email);

        $this->assertSame(2, $result->totalSent);
        $this->assertSame(3, $result->totalRecipients);
        $this->assertCount(1, $result->failedRecipients);
        $this->assertContains('fail@example.com', $result->failedRecipients);
    }

    public function testSendToRecipientMergesExtraContextIntoEmailTemplate(): void
    {
        // Override mailer with mock to capture the sent message
        $mockMailer = $this->createMock(MailerInterface::class);
        $clock = $this->createStub(\App\Time\ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2025-01-01 12:00:00'));
        $service = new EmailService($this->emailRepo, $this->resolver, $mockMailer, $this->em, $clock);

        $event = $this->createStub(Event::class);
        $event->method('getPicture')->willReturn(null);

        $emailTemplate = $this->createStub(Email::class);
        $emailTemplate->method('getSubject')->willReturn('Test Subject');
        $emailTemplate->method('getBody')->willReturn('<p>Test body</p>');
        $emailTemplate->method('getReplyTo')->willReturn('reply@example.com');
        $emailTemplate->method('getAddLoginLinksToFooter')->willReturn(false);
        $emailTemplate->method('getEvent')->willReturn($event);

        $this->emailRepo->method('findOneBy')->willReturn($emailTemplate);

        // Extra context to merge
        $extraContext = [
            'custom_field' => 'custom_value',
            'user_name' => 'John Doe',
            'token' => 'abc123',
        ];

        // Capture the message sent to mailer
        $capturedMessage = null;
        $mockMailer->expects($this->once())
            ->method('send')
            ->willReturnCallback(static function ($message) use (&$capturedMessage) {
                $capturedMessage = $message;
            });

        $service->sendToRecipient(
            EmailPurpose::RSVP,
            'recipient@example.com',
            $event,
            'en',
            $extraContext
        );

        // Verify message was sent
        $this->assertInstanceOf(\Symfony\Bridge\Twig\Mime\TemplatedEmail::class, $capturedMessage);

        // Access the context using reflection (since it's private)
        $reflection = new \ReflectionClass($capturedMessage);
        $contextProperty = $reflection->getProperty('context');
        $contextProperty->setAccessible(true);
        $context = $contextProperty->getValue($capturedMessage);

        // Verify standard context fields are present
        $this->assertArrayHasKey('body', $context);
        $this->assertArrayHasKey('links', $context);
        $this->assertArrayHasKey('img', $context);
        $this->assertArrayHasKey('locale', $context);

        // Verify extra context fields were merged
        $this->assertArrayHasKey('custom_field', $context);
        $this->assertSame('custom_value', $context['custom_field']);
        $this->assertArrayHasKey('user_name', $context);
        $this->assertSame('John Doe', $context['user_name']);
        $this->assertArrayHasKey('token', $context);
        $this->assertSame('abc123', $context['token']);

        // Verify standard values are still correct
        $this->assertSame('<p>Test body</p>', $context['body']);
        $this->assertFalse($context['links']);
        $this->assertSame('en', $context['locale']);
    }

    public function testSendToRecipientExtraContextOverridesStandardContext(): void
    {
        // Override mailer with mock to capture the sent message
        $mockMailer = $this->createMock(MailerInterface::class);
        $clock = $this->createStub(\App\Time\ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2025-01-01 12:00:00'));
        $service = new EmailService($this->emailRepo, $this->resolver, $mockMailer, $this->em, $clock);

        $event = $this->createStub(Event::class);
        $event->method('getPicture')->willReturn(null);

        $emailTemplate = $this->createStub(Email::class);
        $emailTemplate->method('getSubject')->willReturn('Test Subject');
        $emailTemplate->method('getBody')->willReturn('<p>Original body</p>');
        $emailTemplate->method('getReplyTo')->willReturn('reply@example.com');
        $emailTemplate->method('getAddLoginLinksToFooter')->willReturn(true);
        $emailTemplate->method('getEvent')->willReturn($event);

        $this->emailRepo->method('findOneBy')->willReturn($emailTemplate);

        // Extra context that overrides standard fields
        $extraContext = [
            'body' => '<p>Custom body override</p>',
            'links' => false,
            'locale' => 'sv', // Override the passed locale
        ];

        // Capture the message sent to mailer
        $capturedMessage = null;
        $mockMailer->expects($this->once())
            ->method('send')
            ->willReturnCallback(static function ($message) use (&$capturedMessage) {
                $capturedMessage = $message;
            });

        $service->sendToRecipient(
            EmailPurpose::RSVP,
            'recipient@example.com',
            $event,
            'en',  // Pass 'en' but extraContext overrides with 'sv'
            $extraContext
        );

        // Access the context using reflection
        $reflection = new \ReflectionClass($capturedMessage);
        $contextProperty = $reflection->getProperty('context');
        $contextProperty->setAccessible(true);
        $context = $contextProperty->getValue($capturedMessage);

        // Verify extraContext values override standard values
        $this->assertSame('<p>Custom body override</p>', $context['body'], 'extraContext should override body');
        $this->assertFalse($context['links'], 'extraContext should override links');
        $this->assertSame('sv', $context['locale'], 'extraContext should override locale');
    }

    public function testSendToRecipientWorksWithoutExtraContext(): void
    {
        // Verify backwards compatibility - extraContext is optional
        $mockMailer = $this->createMock(MailerInterface::class);
        $clock = $this->createStub(\App\Time\ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2025-01-01 12:00:00'));
        $service = new EmailService($this->emailRepo, $this->resolver, $mockMailer, $this->em, $clock);

        $event = $this->createStub(Event::class);
        $event->method('getPicture')->willReturn(null);

        $emailTemplate = $this->createStub(Email::class);
        $emailTemplate->method('getSubject')->willReturn('Test Subject');
        $emailTemplate->method('getBody')->willReturn('<p>Test body</p>');
        $emailTemplate->method('getReplyTo')->willReturn('reply@example.com');
        $emailTemplate->method('getAddLoginLinksToFooter')->willReturn(true);
        $emailTemplate->method('getEvent')->willReturn($event);

        $this->emailRepo->method('findOneBy')->willReturn($emailTemplate);

        // Capture the message
        $capturedMessage = null;
        $mockMailer->expects($this->once())
            ->method('send')
            ->willReturnCallback(static function ($message) use (&$capturedMessage) {
                $capturedMessage = $message;
            });

        // Call without extraContext (should work fine)
        $service->sendToRecipient(
            EmailPurpose::RSVP,
            'recipient@example.com',
            $event,
            'fi'
        );

        $this->assertInstanceOf(\Symfony\Bridge\Twig\Mime\TemplatedEmail::class, $capturedMessage);

        // Verify standard context is present
        $reflection = new \ReflectionClass($capturedMessage);
        $contextProperty = $reflection->getProperty('context');
        $contextProperty->setAccessible(true);
        $context = $contextProperty->getValue($capturedMessage);

        $this->assertArrayHasKey('body', $context);
        $this->assertArrayHasKey('links', $context);
        $this->assertArrayHasKey('locale', $context);
        $this->assertSame('fi', $context['locale']);
    }

    public function testSendToRecipientSetsFromForActiveMemberInfoPackageFinnish(): void
    {
        $mockMailer = $this->createMock(MailerInterface::class);
        $clock = $this->createStub(\App\Time\ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2025-01-01 12:00:00'));
        $service = new EmailService($this->emailRepo, $this->resolver, $mockMailer, $this->em, $clock);

        $emailTemplate = $this->createStub(Email::class);
        $emailTemplate->method('getPurpose')->willReturn(EmailPurpose::ACTIVE_MEMBER_INFO_PACKAGE);
        $emailTemplate->method('getSubject')->willReturn('Active Member Info');
        $emailTemplate->method('getBody')->willReturn('<p>Test body</p>');
        $emailTemplate->method('getReplyTo')->willReturn('hallitus@entropy.fi');
        $emailTemplate->method('getAddLoginLinksToFooter')->willReturn(false);
        $emailTemplate->method('getEvent')->willReturn(null);

        $this->emailRepo->method('findOneBy')->willReturn($emailTemplate);

        // Capture the message sent to mailer
        $capturedMessage = null;
        $mockMailer->expects($this->once())
            ->method('send')
            ->willReturnCallback(static function ($message) use (&$capturedMessage) {
                $capturedMessage = $message;
            });

        $service->sendToRecipient(
            EmailPurpose::ACTIVE_MEMBER_INFO_PACKAGE,
            'recipient@example.com',
            null,
            'fi'
        );

        // Verify FROM was set with Finnish sender name
        $this->assertInstanceOf(\Symfony\Bridge\Twig\Mime\TemplatedEmail::class, $capturedMessage);
        $from = $capturedMessage->getFrom();
        $this->assertCount(1, $from);
        $this->assertSame('hallitus@entropy.fi', $from[0]->getAddress());
        $this->assertSame('Entropyn Hallitus', $from[0]->getName());
    }

    public function testSendToRecipientSetsFromForActiveMemberInfoPackageEnglish(): void
    {
        $mockMailer = $this->createMock(MailerInterface::class);
        $clock = $this->createStub(\App\Time\ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2025-01-01 12:00:00'));
        $service = new EmailService($this->emailRepo, $this->resolver, $mockMailer, $this->em, $clock);

        $emailTemplate = $this->createStub(Email::class);
        $emailTemplate->method('getPurpose')->willReturn(EmailPurpose::ACTIVE_MEMBER_INFO_PACKAGE);
        $emailTemplate->method('getSubject')->willReturn('Active Member Info');
        $emailTemplate->method('getBody')->willReturn('<p>Test body</p>');
        $emailTemplate->method('getReplyTo')->willReturn('hallitus@entropy.fi');
        $emailTemplate->method('getAddLoginLinksToFooter')->willReturn(false);
        $emailTemplate->method('getEvent')->willReturn(null);

        $this->emailRepo->method('findOneBy')->willReturn($emailTemplate);

        $capturedMessage = null;
        $mockMailer->expects($this->once())
            ->method('send')
            ->willReturnCallback(static function ($message) use (&$capturedMessage) {
                $capturedMessage = $message;
            });

        $service->sendToRecipient(
            EmailPurpose::ACTIVE_MEMBER_INFO_PACKAGE,
            'recipient@example.com',
            null,
            'en'
        );

        // Verify FROM was set with English sender name
        $from = $capturedMessage->getFrom();
        $this->assertCount(1, $from);
        $this->assertSame('hallitus@entropy.fi', $from[0]->getAddress());
        $this->assertSame('Entropy Board', $from[0]->getName());
    }

    public function testSendToRecipientUsesGlobalConfigForOtherPurposes(): void
    {
        $mockMailer = $this->createMock(MailerInterface::class);
        $clock = $this->createStub(\App\Time\ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2025-01-01 12:00:00'));
        $service = new EmailService($this->emailRepo, $this->resolver, $mockMailer, $this->em, $clock);

        $emailTemplate = $this->createStub(Email::class);
        $emailTemplate->method('getPurpose')->willReturn(EmailPurpose::RSVP);
        $emailTemplate->method('getSubject')->willReturn('RSVP Confirmation');
        $emailTemplate->method('getBody')->willReturn('<p>Test</p>');
        $emailTemplate->method('getReplyTo')->willReturn('hallitus@entropy.fi');
        $emailTemplate->method('getAddLoginLinksToFooter')->willReturn(false);
        $emailTemplate->method('getEvent')->willReturn(null);

        $this->emailRepo->method('findOneBy')->willReturn($emailTemplate);

        $capturedMessage = null;
        $mockMailer->expects($this->once())
            ->method('send')
            ->willReturnCallback(static function ($message) use (&$capturedMessage) {
                $capturedMessage = $message;
            });

        $service->sendToRecipient(
            EmailPurpose::RSVP,
            'recipient@example.com',
            null,
            'fi'
        );

        // Verify FROM was NOT explicitly set (uses global config: webmaster@entropy.fi)
        $from = $capturedMessage->getFrom();
        $this->assertEmpty($from, 'FROM should not be set explicitly, should use global config');
    }
}
