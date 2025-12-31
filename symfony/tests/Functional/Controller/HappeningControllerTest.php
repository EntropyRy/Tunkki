<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\HappeningBooking;
use App\Entity\Member;
use App\Factory\EventFactory;
use App\Factory\HappeningFactory;
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
        [$user, $_client] = $this->loginAsMember();
        $member = $user->getMember();
        $event = EventFactory::new()->published()->create();
        $happening = HappeningFactory::new()
            ->released()
            ->forEvent($event)
            ->withOwner($member)
            ->create();
        $happeningEntity = $happening instanceof Proxy ? $happening->_real() : $happening;

        $booking = $this->createBooking($member, $happeningEntity);

        $this->client->request('GET', \sprintf('/happening/%d/remove', $booking->getId()));
        $this->assertResponseStatusCodeSame(302);

        $this->em()->clear();
        $reloaded = $this->em()->getRepository(HappeningBooking::class)->find($booking->getId());
        self::assertNull($reloaded);
    }

    public function testRemoveDoesNotDeleteForOtherMember(): void
    {
        [$ownerUser, $_client] = $this->loginAsMember();
        $owner = $ownerUser->getMember();
        $event = EventFactory::new()->published()->create();
        $happening = HappeningFactory::new()
            ->released()
            ->forEvent($event)
            ->withOwner($owner)
            ->create();
        $happeningEntity = $happening instanceof Proxy ? $happening->_real() : $happening;

        $booking = $this->createBooking($owner, $happeningEntity);

        [$otherUser, $_clientTwo] = $this->loginAsMember();
        $this->client->loginUser($otherUser);

        $this->client->request('GET', \sprintf('/happening/%d/remove', $booking->getId()));
        $this->assertResponseStatusCodeSame(302);

        $this->em()->clear();
        $reloaded = $this->em()->getRepository(HappeningBooking::class)->find($booking->getId());
        self::assertNotNull($reloaded);
    }

    private function createBooking(Member $member, $happening): HappeningBooking
    {
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
