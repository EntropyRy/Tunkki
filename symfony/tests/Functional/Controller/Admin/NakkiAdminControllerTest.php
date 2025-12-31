<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Controller\Admin\NakkiAdminController;
use App\Entity\Nakki;
use App\Factory\EventFactory;
use App\Factory\NakkiFactory;
use App\Factory\NakkikoneFactory;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use PHPUnit\Framework\Attributes\Group;
use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Persistence\Proxy;

#[Group('admin')]
#[Group('nakki')]
final class NakkiAdminControllerTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('fi');
    }

    public function testCloneActionCreatesCopyAndRedirects(): void
    {
        $nakkiProxy = NakkiFactory::new()->create();
        $nakki = $nakkiProxy instanceof Proxy ? $nakkiProxy->_real() : $nakkiProxy;

        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $admin = static::getContainer()->get('entropy.admin.nakki');
        $url = $admin->generateUrl('clone', ['id' => $nakki->getId()]);

        $countBefore = $this->em()->getRepository(Nakki::class)->count([]);

        $this->client->request('GET', $url);
        $this->assertResponseStatusCodeSame(302);

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('.alert.alert-success');
        $this->client->assertSelectorTextContains('.alert.alert-success', 'Cloned successfully');

        $countAfter = $this->em()->getRepository(Nakki::class)->count([]);
        self::assertSame($countBefore + 1, $countAfter);
    }

    public function testCloneActionThrowsWhenSubjectMissing(): void
    {
        $admin = static::getContainer()->get('entropy.admin.nakki');
        $admin->setSubject(null);

        $controller = new NakkiAdminController();
        $adminRef = new \ReflectionProperty(CRUDController::class, 'admin');
        $adminRef->setAccessible(true);
        $adminRef->setValue($controller, $admin);

        $this->expectException(\LogicException::class);

        $controller->cloneAction();
    }

    public function testPreCreateSetsStartAtFromEventDate(): void
    {
        $event = EventFactory::new()->published()->create([
            'url' => 'nakki-precreate-'.uniqid('', true),
        ]);
        $nakkikone = NakkikoneFactory::new()->create([
            'event' => $event,
        ]);
        $nakkiProxy = NakkiFactory::new()->create([
            'nakkikone' => $nakkikone,
            'startAt' => new \DateTimeImmutable('1999-01-01 10:00:00'),
        ]);
        $nakki = $nakkiProxy instanceof Proxy ? $nakkiProxy->_real() : $nakkiProxy;

        $controller = new NakkiAdminController();
        $method = new \ReflectionMethod(NakkiAdminController::class, 'preCreate');
        $method->setAccessible(true);

        $method->invoke($controller, new Request(), $nakki);

        self::assertSame(
            $event->getEventDate()->format('Y-m-d H:i'),
            $nakki->getStartAt()->format('Y-m-d H:i'),
        );
    }

    public function testPreCreateUsesNowWhenNoEvent(): void
    {
        $object = new class {
            public ?\DateTimeImmutable $startAt = null;

            public function getEvent(): ?object
            {
                return null;
            }

            public function setStartAt(\DateTimeImmutable $startAt): void
            {
                $this->startAt = $startAt;
            }
        };

        $before = new \DateTimeImmutable();

        $controller = new NakkiAdminController();
        $method = new \ReflectionMethod(NakkiAdminController::class, 'preCreate');
        $method->setAccessible(true);
        $method->invoke($controller, new Request(), $object);

        $after = new \DateTimeImmutable();

        self::assertInstanceOf(\DateTimeImmutable::class, $object->startAt);
        self::assertGreaterThanOrEqual($before->getTimestamp(), $object->startAt->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $object->startAt->getTimestamp());
    }
}
