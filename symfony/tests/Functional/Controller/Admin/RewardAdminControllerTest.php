<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\Rental\Booking\Reward;
use App\Entity\User;
use App\Factory\MemberFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use PHPUnit\Framework\Attributes\Group;

#[Group('admin')]
final class RewardAdminControllerTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('fi');
    }

    public function testMakePaidMarksRewardPaidAndSetsHandler(): void
    {
        [$handler, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');
        $member = MemberFactory::new()->create();
        $reward = $this->createReward($member->getUser(), '12.50', 1);

        $this->client->request('GET', "/admin/reward/{$reward->getId()}/makepaid");

        $this->assertResponseRedirects();
        $location = $this->client->getResponse()->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertSame('/admin/reward/list', parse_url($location, \PHP_URL_PATH));

        $this->em()->clear();
        $updated = $this->findOneOrFail(Reward::class, ['id' => $reward->getId()]);

        self::assertTrue($updated->getPaid());
        self::assertInstanceOf(\DateTimeImmutable::class, $updated->getPaidDate());
        self::assertSame($handler->getId(), $updated->getPaymentHandledBy()?->getId());
    }

    public function testPrepareEvenoutRendersButtonAndTotals(): void
    {
        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');
        $memberA = MemberFactory::new()->create();
        $memberB = MemberFactory::new()->create();
        $this->createReward($memberA->getUser(), '10.00', 1);
        $this->createReward($memberB->getUser(), '5.00', 2);

        $this->client->request('GET', '/admin/reward/evenout/prepare');

        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorTextContains('h2', 'Old distribution');
        $this->client->assertSelectorExists('a.btn.btn-primary[href="/admin/reward/evenout/make"]');
    }

    public function testEvenoutCalculatesDistributionAndRedirects(): void
    {
        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');
        $memberA = MemberFactory::new()->create();
        $memberB = MemberFactory::new()->create();
        $rewardA = $this->createReward($memberA->getUser(), '20.00', 1);
        $rewardB = $this->createReward($memberB->getUser(), '10.00', 3);

        $this->client->request('GET', '/admin/reward/evenout/make');

        $this->assertResponseRedirects();
        $location = $this->client->getResponse()->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertSame('/admin/reward/list', parse_url($location, \PHP_URL_PATH));

        $this->em()->clear();
        $updatedA = $this->findOneOrFail(Reward::class, ['id' => $rewardA->getId()]);
        $updatedB = $this->findOneOrFail(Reward::class, ['id' => $rewardB->getId()]);

        self::assertEqualsWithDelta(7.5, (float) $updatedA->getEvenout(), 0.0001);
        self::assertEqualsWithDelta(22.5, (float) $updatedB->getEvenout(), 0.0001);
    }

    private function createReward(User $user, string $amount, int $weight): Reward
    {
        $managedUser = null;
        if (null !== $user->getId()) {
            $managedUser = $this->em()->getRepository(User::class)->find($user->getId());
        }
        if (!$managedUser instanceof User) {
            $this->em()->persist($user);
            $this->em()->flush();
            $managedUser = $user;
        }

        $reward = new Reward();
        $reward->setUser($managedUser);
        $reward->setReward($amount);
        $reward->setWeight($weight);

        $this->em()->persist($reward);
        $this->em()->flush();

        return $reward;
    }
}
