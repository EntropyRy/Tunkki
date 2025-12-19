<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\Event;
use App\Entity\Sonata\SonataMediaMedia;
use App\Entity\Ticket;
use App\Factory\EventFactory;
use App\Factory\TicketFactory;
use App\Service\Email\EmailService;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\Attributes\Group;
use Zenstruck\Foundry\Test\Factories;

#[Group('admin')]
#[Group('ticket')]
final class TicketAdminControllerTest extends FixturesWebTestCase
{
    use Factories;
    use LoginHelperTrait;

    private ObjectManager $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('fi');
        $this->entityManager = $this->em();
    }

    public function testSendQrCodeEmailActionUsesEmailServiceInEventChildAdmin(): void
    {
        $event = EventFactory::new()->published()->create(['url' => 'test-event-'.uniqid('', true)]);
        $ticket = TicketFactory::new()
            ->forEvent($event)
            ->withEmail('ticket@example.com')
            ->withReferenceNumber(123456)
            ->withName('Test Ticket')
            ->paid()
            ->create();

        $spy = new SpyEmailService();
        static::getContainer()->set(EmailService::class, $spy);

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->client->request(
            'GET',
            \sprintf(
                '/admin/event/%d/ticket/%d/send-qr-code-email',
                (int) $event->getId(),
                (int) $ticket->getId(),
            ),
        );

        $this->assertResponseStatusCodeSame(302);
        $this->client->followRedirect();
        $this->client->assertSelectorExists('.alert.alert-success');
        $this->client->assertSelectorTextContains('.alert.alert-success', 'QR-code email sent!');

        $this->assertCount(1, $spy->calls);
        $call = $spy->calls[0];
        $this->assertSame((int) $event->getId(), $call['event_id']);
        $this->assertSame('ticket@example.com', $call['recipient_email']);
        $this->assertCount(1, $call['qrs']);
        $this->assertSame('Test Ticket', $call['qrs'][0]['name']);
        $this->assertIsString($call['qrs'][0]['qr']);
        $this->assertNotSame('', $call['qrs'][0]['qr']);
    }

    public function testGiveActionMarksTicketGivenWhenOwnerPresent(): void
    {
        $event = EventFactory::new()->published()->create(['url' => 'test-event-'.uniqid('', true)]);
        $ticket = TicketFactory::new()
            ->forEvent($event)
            ->withEmail('ticket@example.com')
            ->withReferenceNumber(123456)
            ->withName('Test Ticket')
            ->paid()
            ->create();

        $this->assertNull($ticket->getOwner());
        $this->assertFalse((bool) $ticket->isGiven());

        $member = \App\Factory\MemberFactory::new()->create();
        $ticket->setOwner($member);
        $this->entityManager->flush();

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->client->request(
            'GET',
            \sprintf(
                '/admin/event/%d/ticket/%d/give',
                (int) $event->getId(),
                (int) $ticket->getId(),
            ),
        );
        $this->assertResponseStatusCodeSame(302);

        $this->entityManager->clear();
        $reloaded = $this->entityManager->getRepository(Ticket::class)->find($ticket->getId());
        $this->assertInstanceOf(Ticket::class, $reloaded);
        $this->assertTrue((bool) $reloaded->isGiven());
    }

    public function testGiveActionDoesNotMarkTicketGivenWhenOwnerMissing(): void
    {
        $event = EventFactory::new()->published()->create(['url' => 'test-event-'.uniqid('', true)]);
        $ticket = TicketFactory::new()
            ->forEvent($event)
            ->withEmail('ticket@example.com')
            ->withReferenceNumber(123456)
            ->withName('Test Ticket')
            ->paid()
            ->create();

        $this->assertNull($ticket->getOwner());
        $this->assertFalse((bool) $ticket->isGiven());

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->client->request(
            'GET',
            \sprintf(
                '/admin/event/%d/ticket/%d/give',
                (int) $event->getId(),
                (int) $ticket->getId(),
            ),
        );
        $this->assertResponseStatusCodeSame(302);
        $this->client->followRedirect();
        $this->client->assertSelectorExists('.alert.alert-warning');

        $this->entityManager->clear();
        $reloaded = $this->entityManager->getRepository(Ticket::class)->find($ticket->getId());
        $this->assertInstanceOf(Ticket::class, $reloaded);
        $this->assertFalse((bool) $reloaded->isGiven());
    }
}

final class SpyEmailService extends EmailService
{
    /** @var list<array{event_id:int, recipient_email:string, qrs:array, img_present:bool}> */
    public array $calls = [];

    public function __construct()
    {
        // bypass parent constructor
    }

    public function sendTicketQrEmails(Event $event, string $recipientEmail, array $qrs, ?SonataMediaMedia $img = null): void
    {
        $this->calls[] = [
            'event_id' => (int) $event->getId(),
            'recipient_email' => $recipientEmail,
            'qrs' => $qrs,
            'img_present' => null !== $img,
        ];
    }
}
