<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Happening;
use App\Entity\HappeningBooking;
use App\Entity\Member;
use App\Factory\EventFactory;
use App\Factory\HappeningFactory;
use App\Factory\MemberFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use PHPUnit\Framework\Attributes\Group;
use Zenstruck\Foundry\Persistence\Proxy;

#[Group('happening')]
final class HappeningControllerTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('en');
    }

    public function testShowRendersPrevNextFromRepository(): void
    {
        [$user, $_client] = $this->loginAsMember();
        $member = $user->getMember();

        $event = EventFactory::new()->published()->create();
        $year = $event->getEventDate()->format('Y');

        $first = HappeningFactory::new()
            ->released()
            ->forEvent($event)
            ->at(new \DateTimeImmutable('2030-01-01 10:00:00'))
            ->create();
        $middle = HappeningFactory::new()
            ->released()
            ->forEvent($event)
            ->at(new \DateTimeImmutable('2030-01-01 12:00:00'))
            ->create();
        $last = HappeningFactory::new()
            ->released()
            ->forEvent($event)
            ->at(new \DateTimeImmutable('2030-01-01 14:00:00'))
            ->create();

        $middleEntity = $middle instanceof Proxy ? $middle->_real() : $middle;
        $firstEntity = $first instanceof Proxy ? $first->_real() : $first;
        $lastEntity = $last instanceof Proxy ? $last->_real() : $last;

        $this->client->request(
            'GET',
            \sprintf(
                '/en/%s/%s/happening/%s',
                $year,
                $event->getUrl(),
                $middleEntity->getSlugEn(),
            ),
        );

        $this->assertResponseIsSuccessful();

        $prevHref = \sprintf(
            '/en/%s/%s/happening/%s',
            $year,
            $event->getUrl(),
            $firstEntity->getSlugEn(),
        );
        $nextHref = \sprintf(
            '/en/%s/%s/happening/%s',
            $year,
            $event->getUrl(),
            $lastEntity->getSlugEn(),
        );

        $this->client->assertSelectorExists(\sprintf('a[href="%s"]', $prevHref));
        $this->client->assertSelectorExists(\sprintf('a[href="%s"]', $nextHref));
    }

    public function testRemoveDeletesBookingForMember(): void
    {
        $member = MemberFactory::new()->create(['emailVerified' => true]);
        $managedMember = $this->em()->getRepository(Member::class)->find($member->getId());
        if ($managedMember instanceof Member) {
            $member = $managedMember;
        }
        $this->client->loginUser($member->getUser());
        $this->stabilizeSessionAfterLogin();
        $event = EventFactory::new()->published()->create();
        $happening = HappeningFactory::new()
            ->released()
            ->forEvent($event)
            ->create();
        $happeningEntity = $happening instanceof Proxy ? $happening->_real() : $happening;
        $memberRef = $this->em()->getReference(Member::class, $member->getId());
        $happeningManaged = $this->em()->getRepository(Happening::class)->find($happeningEntity->getId());
        if ($happeningManaged instanceof Happening) {
            $happeningManaged->addOwner($memberRef);
            $this->em()->flush();
        }

        $booking = $this->createBooking($member, $happeningEntity);

        $this->client->request('GET', \sprintf('/happening/%d/remove', $booking->getId()));
        $this->assertResponseStatusCodeSame(302);

        $this->em()->clear();
        $reloaded = $this->em()->getRepository(HappeningBooking::class)->find($booking->getId());
        self::assertNull($reloaded);
    }

    public function testRemoveDoesNotDeleteForOtherMember(): void
    {
        $owner = MemberFactory::new()->create(['emailVerified' => true]);
        $managedOwner = $this->em()->getRepository(Member::class)->find($owner->getId());
        if ($managedOwner instanceof Member) {
            $owner = $managedOwner;
        }
        $this->client->loginUser($owner->getUser());
        $this->stabilizeSessionAfterLogin();
        $event = EventFactory::new()->published()->create();
        $happening = HappeningFactory::new()
            ->released()
            ->forEvent($event)
            ->create();
        $happeningEntity = $happening instanceof Proxy ? $happening->_real() : $happening;
        $ownerRef = $this->em()->getReference(Member::class, $owner->getId());
        $happeningManaged = $this->em()->getRepository(Happening::class)->find($happeningEntity->getId());
        if ($happeningManaged instanceof Happening) {
            $happeningManaged->addOwner($ownerRef);
            $this->em()->flush();
        }

        $booking = $this->createBooking($owner, $happeningEntity);

        $other = MemberFactory::new()->create(['emailVerified' => true]);
        $managedOther = $this->em()->getRepository(Member::class)->find($other->getId());
        if ($managedOther instanceof Member) {
            $other = $managedOther;
        }
        $this->client->loginUser($other->getUser());
        $this->stabilizeSessionAfterLogin();

        $this->client->request('GET', \sprintf('/happening/%d/remove', $booking->getId()));
        $this->assertResponseStatusCodeSame(302);

        $this->em()->clear();
        $reloaded = $this->em()->getRepository(HappeningBooking::class)->find($booking->getId());
        self::assertNotNull($reloaded);
    }

    public function testMemberHappeningCollections(): void
    {
        $member = new Member();
        $happening = new Happening();

        $member->addHappening($happening);
        $this->assertCount(1, $member->getHappenings());
        $this->assertCount(1, $happening->getOwners());

        $member->removeHappening($happening);
        $this->assertCount(0, $member->getHappenings());
        $this->assertCount(0, $happening->getOwners());

        $booking = new HappeningBooking();
        $member->addHappeningBooking($booking);
        $this->assertCount(1, $member->getHappeningBooking());
        $this->assertSame($member, $booking->getMember());

        $member->removeHappeningBooking($booking);
        $this->assertCount(0, $member->getHappeningBooking());
        $this->assertNull($booking->getMember());
    }

    private function createBooking(Member $member, Happening $happening): HappeningBooking
    {
        $managedMember = $this->em()->getRepository(Member::class)->find($member->getId());
        if ($managedMember instanceof Member) {
            $member = $managedMember;
        }
        $happening = $happening instanceof Proxy ? $happening->_real() : $happening;
        $managed = $this->em()->getRepository(Happening::class)->find($happening->getId());
        if ($managed instanceof Happening) {
            $happening = $managed;
        }

        $booking = new HappeningBooking();
        $booking->setMember($member);
        $booking->setHappening($happening);
        $booking->setCreatedAt(new \DateTimeImmutable('2030-02-03 14:05:00'));
        $this->em()->persist($booking);
        $this->em()->flush();

        self::assertSame(
            '03.02. 14:05 '.$member->getName(),
            (string) $booking,
        );
        self::assertSame(
            '2030-02-03 14:05:00',
            $booking->getCreatedAt()->format('Y-m-d H:i:s'),
        );

        return $booking;
    }
}
