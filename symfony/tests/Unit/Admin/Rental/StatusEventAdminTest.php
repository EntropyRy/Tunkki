<?php

declare(strict_types=1);

namespace App\Tests\Unit\Admin\Rental;

use App\Admin\Rental\StatusEventAdmin;
use App\Entity\Member;
use App\Entity\Rental\Inventory\Item;
use App\Entity\Rental\StatusEvent;
use App\Entity\User;
use App\Service\MattermostNotifierService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class StatusEventAdminTest extends TestCase
{
    public function testPrePersistLocksOtherItemFlagsWhenDecommissioning(): void
    {
        $item = new Item();
        $item
            ->setCannotBeRented(false)
            ->setNeedsFixing(false)
            ->setForSale(false)
            ->setToSpareParts(false)
            ->setDecommissioned(false);

        // Simulate submitted changes in a single status event.
        $item
            ->setCannotBeRented(true)
            ->setNeedsFixing(true)
            ->setForSale(true)
            ->setToSpareParts(true)
            ->setDecommissioned(true);

        $event = new StatusEvent();
        $event->setItem($item);

        $admin = $this->createAdmin(
            user: $this->createUserStub(),
            mm: $this->createStub(MattermostNotifierService::class),
            originals: [
                spl_object_id($item) => [
                    'cannotBeRented' => false,
                    'needsFixing' => false,
                    'forSale' => false,
                    'toSpareParts' => false,
                    'decommissioned' => false,
                ],
            ],
        );

        $admin->prePersist($event);

        self::assertTrue($item->getDecommissioned(), 'Decommissioned state should be applied.');
        self::assertFalse($item->getCannotBeRented(), 'Cannot-be-rented should remain unchanged while decommissioning.');
        self::assertFalse($item->getNeedsFixing(), 'Needs-fixing should remain unchanged while decommissioning.');
        self::assertFalse((bool) $item->getForSale(), 'For-sale should remain unchanged while decommissioning.');
        self::assertFalse($item->getToSpareParts(), 'To-spare-parts should remain unchanged while decommissioning.');
    }

    public function testPrePersistAllowsUndecommissioningWhileLockingOtherFlags(): void
    {
        $item = new Item();
        $item
            ->setCannotBeRented(true)
            ->setNeedsFixing(false)
            ->setForSale(false)
            ->setToSpareParts(false)
            ->setDecommissioned(true);

        // Simulate attempted updates while un-decommissioning.
        $item
            ->setCannotBeRented(false)
            ->setNeedsFixing(true)
            ->setForSale(true)
            ->setToSpareParts(true)
            ->setDecommissioned(false);

        $event = new StatusEvent();
        $event->setItem($item);

        $admin = $this->createAdmin(
            user: $this->createUserStub(),
            mm: $this->createStub(MattermostNotifierService::class),
            originals: [
                spl_object_id($item) => [
                    'cannotBeRented' => true,
                    'needsFixing' => false,
                    'forSale' => false,
                    'toSpareParts' => false,
                    'decommissioned' => true,
                ],
            ],
        );

        $admin->prePersist($event);

        self::assertFalse($item->getDecommissioned(), 'Item should be allowed to un-decommission.');
        self::assertTrue($item->getCannotBeRented(), 'Cannot-be-rented should remain unchanged during undecommission.');
        self::assertFalse($item->getNeedsFixing(), 'Needs-fixing should remain unchanged during undecommission.');
        self::assertFalse((bool) $item->getForSale(), 'For-sale should remain unchanged during undecommission.');
        self::assertFalse($item->getToSpareParts(), 'To-spare-parts should remain unchanged during undecommission.');
    }

    public function testFormatChangesBuildsReadableDeltaForItemStatuses(): void
    {
        $admin = $this->createAdmin(
            user: $this->createUserStub(),
            mm: $this->createStub(MattermostNotifierService::class),
            originals: [],
        );

        $labels = $this->invokePrivate($admin, 'itemStatusLabels');
        self::assertIsArray($labels);

        $changes = $this->invokePrivate($admin, 'formatChanges', [
            [
                'cannotBeRented' => false,
                'needsFixing' => false,
                'forSale' => false,
                'toSpareParts' => false,
                'decommissioned' => false,
            ],
            [
                'cannotBeRented' => false,
                'needsFixing' => true,
                'forSale' => false,
                'toSpareParts' => false,
                'decommissioned' => true,
            ],
            $labels,
        ]);
        self::assertIsArray($changes);
        self::assertContains('needs fixing: no -> yes', $changes);
        self::assertContains('decommissioned: no -> yes', $changes);
        self::assertNotContains('cannot be rented: no -> no', $changes);
    }

    public function testFormatChangesBuildsReadableDeltaForBookingStatuses(): void
    {
        $admin = $this->createAdmin(
            user: $this->createUserStub(),
            mm: $this->createStub(MattermostNotifierService::class),
            originals: [],
        );

        $labels = $this->invokePrivate($admin, 'bookingStatusLabels');
        self::assertIsArray($labels);

        $changes = $this->invokePrivate($admin, 'formatChanges', [
            [
                'cancelled' => false,
                'renterConsent' => false,
                'itemsReturned' => false,
                'invoiceSent' => false,
                'paid' => false,
            ],
            [
                'cancelled' => true,
                'renterConsent' => false,
                'itemsReturned' => true,
                'invoiceSent' => false,
                'paid' => false,
            ],
            $labels,
        ]);
        self::assertIsArray($changes);
        self::assertContains('cancelled: no -> yes', $changes);
        self::assertContains('items returned: no -> yes', $changes);
        self::assertNotContains('invoice sent: no -> no', $changes);
    }

    /**
     * @param array<int, array<string, bool>> $originals
     */
    private function createAdmin(
        User $user,
        MattermostNotifierService $mm,
        array $originals,
    ): StatusEventAdmin {
        $token = $this->createStub(TokenInterface::class);
        $token
            ->method('getUser')
            ->willReturn($user);

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage
            ->method('getToken')
            ->willReturn($token);

        $uow = $this->createStub(UnitOfWork::class);
        $uow
            ->method('getOriginalEntityData')
            ->willReturnCallback(static fn (object $entity): array => $originals[spl_object_id($entity)] ?? []);

        $em = $this->createStub(EntityManagerInterface::class);
        $em
            ->method('getUnitOfWork')
            ->willReturn($uow);

        return new StatusEventAdmin($mm, $tokenStorage, $em);
    }

    private function createUserStub(): User
    {
        $member = (new Member())
            ->setEmail('status-event-admin@example.test')
            ->setUsername('status-event-admin');

        $user = new User();
        $user->setMember($member);

        return $user;
    }

    /**
     * @param array<int, mixed> $args
     */
    private function invokePrivate(StatusEventAdmin $admin, string $methodName, array $args = []): mixed
    {
        $method = new \ReflectionMethod(StatusEventAdmin::class, $methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($admin, $args);
    }
}
