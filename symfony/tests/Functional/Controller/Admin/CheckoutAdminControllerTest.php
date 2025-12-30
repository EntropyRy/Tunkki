<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\Checkout;
use App\Factory\CheckoutFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use PHPUnit\Framework\Attributes\Group;

#[Group('admin')]
#[Group('checkout')]
final class CheckoutAdminControllerTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('fi');
    }

    public function testRemoveUnneededActionDeletesExpiredCheckoutsAndRedirects(): void
    {
        $expiredOne = CheckoutFactory::new()->expired()->create();
        $expiredTwo = CheckoutFactory::new()->expired()->create();
        $open = CheckoutFactory::new()->open()->create();

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $admin = static::getContainer()->get('admin.checkout');
        $url = $admin->generateUrl('remove_unneeded');

        $this->client->request('GET', $url);
        $this->assertResponseStatusCodeSame(302);

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('.alert.alert-success');
        $this->client->assertSelectorTextContains('.alert.alert-success', 'Removed: 2');

        $em = $this->em();
        $em->clear();

        self::assertNull(
            $em->getRepository(Checkout::class)->find($expiredOne->getId()),
        );
        self::assertNull(
            $em->getRepository(Checkout::class)->find($expiredTwo->getId()),
        );
        self::assertNotNull(
            $em->getRepository(Checkout::class)->find($open->getId()),
        );
    }
}
