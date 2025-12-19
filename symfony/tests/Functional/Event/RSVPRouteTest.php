<?php

declare(strict_types=1);

namespace App\Tests\Functional\Event;

use App\Entity\RSVP;
use App\Factory\EventFactory;
use App\Factory\MemberFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;

final class RSVPRouteTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('en');
    }

    public function testLoggedInMemberCanRsvpViaPostRoute(): void
    {
        $event = EventFactory::new()->withRsvpEnabled()->create();

        $member = MemberFactory::new()->inactive()->create();
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        $year = $event->getEventDate()->format('Y');
        $slug = (string) $event->getUrl();

        $this->client->request('POST', "/{$year}/{$slug}/rsvp");

        $this->assertResponseStatusCodeSame(302);

        $rsvps = $this->em()->getRepository(RSVP::class)->findBy([
            'event' => $event,
            'member' => $member,
        ]);
        self::assertCount(1, $rsvps);
    }

    public function testLoggedInMemberDuplicateRsvpDoesNotCreateSecondRow(): void
    {
        $event = EventFactory::new()->withRsvpEnabled()->create();

        $member = MemberFactory::new()->inactive()->create();
        $this->loginAsMember($member->getEmail());
        $this->seedClientHome('en');

        $year = $event->getEventDate()->format('Y');
        $slug = (string) $event->getUrl();

        $this->client->request('POST', "/{$year}/{$slug}/rsvp");
        $this->assertResponseStatusCodeSame(302);

        $this->client->request('POST', "/{$year}/{$slug}/rsvp");
        $this->assertResponseStatusCodeSame(302);

        $rsvps = $this->em()->getRepository(RSVP::class)->findBy([
            'event' => $event,
            'member' => $member,
        ]);
        self::assertCount(1, $rsvps);
    }

    public function testAnonymousInvalidSubmissionReturns422(): void
    {
        $event = EventFactory::new()->withRsvpEnabled()->create();

        $year = $event->getEventDate()->format('Y');
        $slug = (string) $event->getUrl();

        $this->client->request('POST', "/{$year}/{$slug}/rsvp", [
            'rsvp' => [
                'firstName' => 'Ada',
                'lastName' => 'Lovelace',
                'email' => 'not-an-email',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }
}
