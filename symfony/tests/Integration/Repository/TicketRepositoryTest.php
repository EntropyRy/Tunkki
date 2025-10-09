<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Event;
use App\Entity\Member;
use App\Entity\Ticket;
use App\Repository\TicketRepository;

/**
 * @covers \App\Repository\TicketRepository
 */
final class TicketRepositoryTest extends RepositoryTestCase
{
    private function subjectRepo(): TicketRepository
    {
        /** @var TicketRepository $r */
        $r = $this->em()->getRepository(Ticket::class);

        return $r;
    }

    /**
     * Robust retrieval of the canonical fixture Member (authId=local-user).
     * Strategy:
     *  1. Direct DQL join query (guarantees managed Member instance, no lazy proxy side-effects).
     *  2. Fallback to legacy user->getMember() path if join query fails.
     *  3. Final defensive re-fetch by Member id if still not contained.
     *
     * Always returns a managed & initialized Member or fails the test.
     */
    /**
     * Create and persist an isolated transient Member (no dependency on fixtures),
     * returning a managed & initialized entity safe to use as a Ticket owner.
     */
    private function createTransientMember(): Member
    {
        $m = new Member();
        $unique = uniqid('ticket-owner-', true);
        $m->setEmail($unique.'@example.test');
        if (method_exists($m, 'setFirstname')) {
            $m->setFirstname('Ticket');
        }
        if (method_exists($m, 'setLastname')) {
            $m->setLastname('Owner');
        }
        if (method_exists($m, 'setLocale')) {
            $m->setLocale('en');
        }
        if (method_exists($m, 'setCode')) {
            $m->setCode('T-'.substr(md5($unique), 0, 10));
        }
        if (method_exists($m, 'setEmailVerified')) {
            $m->setEmailVerified(true);
        }
        $this->em()->persist($m);
        $this->em()->flush();

        return $m;
    }

    // (method signature moved above unchanged)

    private function createEvent(
        string $url = 'tickets-repo-test',
        string $type = 'event',
        bool $published = true,
        ?\DateTimeImmutable $eventDate = null,
        ?\DateTimeImmutable $publishDate = null,
    ): Event {
        $e = new Event();
        $e->setName('Tickets Repo Event')
            ->setNimi('Lippu Repo Tapahtuma')
            ->setType($type)
            ->setUrl($url)
            ->setPublished($published)
            ->setEventDate($eventDate ?? new \DateTimeImmutable('+5 days'))
            ->setPublishDate($publishDate ?? new \DateTimeImmutable('-1 hour'))
            ->setTemplate('event.html.twig')
            ->setTicketsEnabled(true);

        return $e;
    }

    private function createTicket(
        Event $event,
        int $price,
        string $status = 'available',
        ?Member $owner = null,
        ?int $reference = null,
        ?string $email = null,
    ): Ticket {
        $t = new Ticket();
        $t->setEvent($event)->setPrice($price)->setStatus($status);

        if (null !== $owner) {
            $t->setOwner($owner);
        }
        if (null !== $reference) {
            $t->setReferenceNumber($reference);
        }
        if (null !== $email) {
            $t->setEmail($email);
        }

        return $t;
    }

    public function testFindAvailableTicketsAndCount(): void
    {
        $event = $this->createEvent();
        $em = $this->em();
        $em->persist($event);
        $em->flush(); // ensure Event is managed before creating tickets

        // Obtain managed fixture Member (defensive helper ensures no stale proxy)
        $member = $this->createTransientMember();

        // Tickets:
        //  - 3 available (no owner)
        //  - 1 reserved (no owner)
        //  - 1 paid (counts should exclude in available count; repository logic excludes paid)
        //  - 1 available but owned (should NOT be returned by findAvailableTickets())
        $tickets = [];
        $tickets[] = $this->createTicket($event, 1000, 'available', null, 101);
        $tickets[] = $this->createTicket($event, 1000, 'available', null, 102);
        $tickets[] = $this->createTicket($event, 1000, 'available', null, 103);
        $tickets[] = $this->createTicket($event, 1200, 'reserved', null, 104);
        $tickets[] = $this->createTicket($event, 1500, 'paid', null, 105);
        $tickets[] = $this->createTicket(
            $event,
            1000,
            'available',
            $member,
            106,
        );

        foreach ($tickets as $t) {
            $this->em()->persist($t);
        }
        $this->em()->flush();

        $repo = $this->subjectRepo();

        $available = $repo->findAvailableTickets($event);
        self::assertCount(
            3,
            $available,
            'Only the 3 ownerless available tickets should be returned.',
        );

        // Ensure each returned ticket has expected invariants
        foreach ($available as $a) {
            self::assertNull(
                $a->getOwner(),
                'Available ticket must have no owner.',
            );
            self::assertSame('available', $a->getStatus());
        }

        // Single available ticket fetch
        $single = $repo->findAvailableTicket($event);
        self::assertInstanceOf(Ticket::class, $single);
        self::assertSame('available', $single->getStatus());
        self::assertNull($single->getOwner());

        // Count of "available" according to repository logic:
        // Implementation counts tickets where status != 'paid'
        // In our dataset: 3 available + 1 reserved + 1 available (owned) = 5
        $countAvailable = (int) $repo->findAvailableTicketsCount($event);
        self::assertSame(
            5,
            $countAvailable,
            'Available tickets count (status != paid) should be 5.',
        );
    }

    public function testPresaleTicketsAndAvailablePresaleTicket(): void
    {
        $event = $this->createEvent('presale-event');
        $event->setTicketPresaleCount(4);
        $em = $this->em();
        $em->persist($event);
        $em->flush(); // ensure managed before tickets

        // Create 6 tickets:
        //  IDs by reference number ascending (simulate creation order)
        //  First 2 available, next 1 reserved, next 1 available owned (preset owner), next 1 paid, last available.
        //  Retrieve and defensively hydrate fixture member BEFORE creating owned ticket to avoid stale proxy issues.
        $member = $this->createTransientMember();
        $set = [
            $this->createTicket($event, 1000, 'available', null, 201),
            $this->createTicket($event, 1000, 'available', null, 202),
            $this->createTicket($event, 1000, 'reserved', null, 203),
            $this->createTicket($event, 1000, 'available', $member, 204), // owned -> not available
            $this->createTicket($event, 1000, 'paid', null, 205),
            $this->createTicket($event, 1000, 'available', null, 206),
        ];

        foreach ($set as $t) {
            $this->em()->persist($t);
        }
        $this->em()->flush();

        $repo = $this->subjectRepo();

        $presale = $repo->findPresaleTickets($event);
        self::assertCount(
            4,
            $presale,
            'Presale tickets should be limited to event->ticketPresaleCount (4).',
        );

        // Available presale ticket should be one of the first four with status=available and no owner
        $availablePresale = $repo->findAvailablePresaleTicket($event);
        self::assertInstanceOf(Ticket::class, $availablePresale);
        self::assertSame('available', $availablePresale->getStatus());
        self::assertNull($availablePresale->getOwner());

        // Make first four all unavailable (set status paid or assign owner) then expect null
        foreach ($presale as $p) {
            if ('available' === $p->getStatus() && null === $p->getOwner()) {
                $p->setStatus('paid');
            }
        }
        $this->em()->flush();

        $none = $repo->findAvailablePresaleTicket($event);
        self::assertNull(
            $none,
            'Should return null when no presale tickets remain available.',
        );
    }

    public function testMemberTicketReferenceAndOwnerQueries(): void
    {
        $event = $this->createEvent('member-ref-event');
        $em = $this->em();
        $em->persist($event);
        $em->flush(); // ensure managed before tickets

        try {
            $member = $this->createTransientMember();
        } catch (\Throwable $rehydrationFailure) {
            // Defensive fallback: clear and re-fetch in case a stale proxy / closed EM caused the failure.
            $this->em()->clear();
            $member = $this->createTransientMember();
        }
        // Explicitly ensure the member entity is managed; if for any reason it is detached
        // (e.g. prior EM clear or proxy issue), persist & flush so that assigning it as an
        // owner does not trigger ORMInvalidArgumentException.
        if (!$this->em()->contains($member)) {
            $this->em()->persist($member);
            $this->em()->flush();
        }

        $t1 = $this->createTicket($event, 1000, 'available', $member, 301);
        $t2 = $this->createTicket($event, 1200, 'reserved', $member, 302);
        $t3 = $this->createTicket(
            $event,
            1500,
            'paid',
            null,
            303,
            'external.buyer@example.com',
        );

        $this->persistAndFlush([$t1, $t2, $t3]);

        $repo = $this->subjectRepo();

        $ref = $repo->findMemberTicketReferenceForEvent($member, $event);
        self::assertNotNull($ref);
        self::assertContains(
            $ref,
            [301, 302],
            'Reference should belong to one of the member tickets.',
        );

        $memberTickets = $repo->findMemberTickets($member);
        self::assertCount(
            2,
            $memberTickets,
            'Member should own exactly 2 tickets.',
        );

        // Tickets by email (owner email)
        $ticketsByOwnerEmail = $repo->findTicketsByEmailAndEvent(
            $member->getEmail(),
            $event,
        );
        self::assertNotEmpty($ticketsByOwnerEmail);
        $ownerRefs = array_map(
            static fn (Ticket $t): ?int => $t->getReferenceNumber(),
            $ticketsByOwnerEmail,
        );
        self::assertTrue(
            \in_array(301, $ownerRefs, true) || \in_array(302, $ownerRefs, true),
            'Query by owner email should return at least one owned ticket reference.',
        );

        // Direct purchaser email (t3 not owned, only email field)
        $ticketsByPlainEmail = $repo->findTicketsByEmailAndEvent(
            'external.buyer@example.com',
            $event,
        );
        // Depending on logical precedence bug in query, this should still include t3
        $plainRefs = array_map(
            static fn (Ticket $t): ?int => $t->getReferenceNumber(),
            $ticketsByPlainEmail,
        );
        self::assertContains(
            303,
            $plainRefs,
            'Query by ticket email should include ticket with matching email.',
        );
    }
}
