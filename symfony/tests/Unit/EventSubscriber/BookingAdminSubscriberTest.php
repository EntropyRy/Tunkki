<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\Booking;
use App\Entity\StatusEvent;
use App\Entity\User;
use App\EventSubscriber\BookingAdminSubscriber;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Event\PersistenceEvent;
use Symfony\Component\Mailer\MailerInterface;

/**
 * @covers \App\EventSubscriber\BookingAdminSubscriber
 */
final class BookingAdminSubscriberTest extends TestCase
{
    private BookingAdminSubscriber $subscriber;
    private MailerInterface $mailer;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = BookingAdminSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey('sonata.admin.event.persistence.post_persist', $events);
        $this->assertArrayHasKey('sonata.admin.event.persistence.pre_persist', $events);
        $this->assertSame('sendEmailNotification', $events['sonata.admin.event.persistence.post_persist']);
        $this->assertSame('updateRewards', $events['sonata.admin.event.persistence.pre_persist']);
    }

    public function testSendEmailNotificationWithEmptyEmail(): void
    {
        $this->subscriber = new BookingAdminSubscriber('', 'from@example.com', $this->mailer, $this->em);

        $booking = $this->createMock(Booking::class);
        $admin = $this->createMock(AdminInterface::class);
        $event = new PersistenceEvent($admin, $booking, PersistenceEvent::TYPE_POST_PERSIST);

        $this->mailer->expects($this->never())->method('send');

        $this->subscriber->sendEmailNotification($event);
    }

    public function testSendEmailNotificationWithZeroEmail(): void
    {
        $this->subscriber = new BookingAdminSubscriber('0', 'from@example.com', $this->mailer, $this->em);

        $booking = $this->createMock(Booking::class);
        $admin = $this->createMock(AdminInterface::class);
        $event = new PersistenceEvent($admin, $booking, PersistenceEvent::TYPE_POST_PERSIST);

        $this->mailer->expects($this->never())->method('send');

        $this->subscriber->sendEmailNotification($event);
    }

    public function testSendEmailNotificationWithNonBookingObject(): void
    {
        $this->subscriber = new BookingAdminSubscriber('admin@example.com', 'from@example.com', $this->mailer, $this->em);

        $admin = $this->createMock(AdminInterface::class);
        $event = new PersistenceEvent($admin, new \stdClass(), PersistenceEvent::TYPE_POST_PERSIST);

        $this->mailer->expects($this->never())->method('send');

        $this->subscriber->sendEmailNotification($event);
    }

    public function testSendEmailNotificationWithValidBooking(): void
    {
        $this->subscriber = new BookingAdminSubscriber('admin@example.com', 'from@example.com', $this->mailer, $this->em);

        $booking = $this->createMock(Booking::class);
        $booking->method('getBookingDate')->willReturn(new \DateTimeImmutable('2025-10-18'));

        $admin = $this->createMock(AdminInterface::class);
        $event = new PersistenceEvent($admin, $booking, PersistenceEvent::TYPE_POST_PERSIST);

        $this->mailer->expects($this->once())->method('send');

        $this->subscriber->sendEmailNotification($event);
    }

    public function testUpdateRewardsWithNonStatusEventObject(): void
    {
        $this->subscriber = new BookingAdminSubscriber('admin@example.com', 'from@example.com', $this->mailer, $this->em);

        $admin = $this->createMock(AdminInterface::class);
        $event = new PersistenceEvent($admin, new \stdClass(), PersistenceEvent::TYPE_PRE_PERSIST);

        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $this->subscriber->updateRewards($event);
    }

    public function testUpdateRewardsWithStatusEventWithoutBooking(): void
    {
        $this->subscriber = new BookingAdminSubscriber('admin@example.com', 'from@example.com', $this->mailer, $this->em);

        $statusEvent = $this->createMock(StatusEvent::class);
        $statusEvent->method('getBooking')->willReturn(null);

        $admin = $this->createMock(AdminInterface::class);
        $event = new PersistenceEvent($admin, $statusEvent, PersistenceEvent::TYPE_PRE_PERSIST);

        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $this->subscriber->updateRewards($event);
    }

    public function testUpdateRewardsWithUnpaidBooking(): void
    {
        $this->subscriber = new BookingAdminSubscriber('admin@example.com', 'from@example.com', $this->mailer, $this->em);

        $booking = $this->createMock(Booking::class);
        $booking->method('getPaid')->willReturn(false);

        $statusEvent = $this->createMock(StatusEvent::class);
        $statusEvent->method('getBooking')->willReturn($booking);

        $admin = $this->createMock(AdminInterface::class);
        $event = new PersistenceEvent($admin, $statusEvent, PersistenceEvent::TYPE_PRE_PERSIST);

        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $this->subscriber->updateRewards($event);
    }

    public function testUpdateRewardsWithPaidBookingZeroPrice(): void
    {
        $this->subscriber = new BookingAdminSubscriber('admin@example.com', 'from@example.com', $this->mailer, $this->em);

        $booking = $this->createMock(Booking::class);
        $booking->method('getPaid')->willReturn(true);
        $booking->method('getActualPrice')->willReturn('0');

        $statusEvent = $this->createMock(StatusEvent::class);
        $statusEvent->method('getBooking')->willReturn($booking);

        $admin = $this->createMock(AdminInterface::class);
        $event = new PersistenceEvent($admin, $statusEvent, PersistenceEvent::TYPE_PRE_PERSIST);

        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $this->subscriber->updateRewards($event);
    }

    public function testUpdateRewardsWithAlreadyPaidBooking(): void
    {
        $this->subscriber = new BookingAdminSubscriber('admin@example.com', 'from@example.com', $this->mailer, $this->em);

        $booking = $this->createMock(Booking::class);
        $booking->method('getPaid')->willReturn(true);
        $booking->method('getActualPrice')->willReturn('100');

        $statusEvent = $this->createMock(StatusEvent::class);
        $statusEvent->method('getBooking')->willReturn($booking);

        $admin = $this->createMock(AdminInterface::class);
        $event = new PersistenceEvent($admin, $statusEvent, PersistenceEvent::TYPE_PRE_PERSIST);

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->method('getOriginalEntityData')->willReturn(['paid' => true]); // Was already paid

        $this->em->method('getUnitOfWork')->willReturn($unitOfWork);
        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $this->subscriber->updateRewards($event);
    }

    public function testUpdateRewardsWithNewlyPaidBookingSameGiverReceiver(): void
    {
        $this->subscriber = new BookingAdminSubscriber('admin@example.com', 'from@example.com', $this->mailer, $this->em);

        $user = $this->createMock(User::class);
        $user->method('getRewards')->willReturn(new ArrayCollection());

        $booking = $this->createMock(Booking::class);
        $booking->method('getPaid')->willReturn(true);
        $booking->method('getActualPrice')->willReturn('100');
        $booking->method('getGivenAwayBy')->willReturn($user);
        $booking->method('getReceivedBy')->willReturn($user);

        $statusEvent = $this->createMock(StatusEvent::class);
        $statusEvent->method('getBooking')->willReturn($booking);

        $admin = $this->createMock(AdminInterface::class);
        $event = new PersistenceEvent($admin, $statusEvent, PersistenceEvent::TYPE_PRE_PERSIST);

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->method('getOriginalEntityData')->willReturn(['paid' => false]); // Newly paid

        $this->em->method('getUnitOfWork')->willReturn($unitOfWork);
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $this->subscriber->updateRewards($event);
    }

    public function testUpdateRewardsWithNewlyPaidBookingDifferentGiverReceiver(): void
    {
        $this->subscriber = new BookingAdminSubscriber('admin@example.com', 'from@example.com', $this->mailer, $this->em);

        $givenAwayBy = $this->createMock(User::class);
        $givenAwayBy->method('getRewards')->willReturn(new ArrayCollection());

        $receivedBy = $this->createMock(User::class);
        $receivedBy->method('getRewards')->willReturn(new ArrayCollection());

        $booking = $this->createMock(Booking::class);
        $booking->method('getPaid')->willReturn(true);
        $booking->method('getActualPrice')->willReturn('100');
        $booking->method('getGivenAwayBy')->willReturn($givenAwayBy);
        $booking->method('getReceivedBy')->willReturn($receivedBy);

        $statusEvent = $this->createMock(StatusEvent::class);
        $statusEvent->method('getBooking')->willReturn($booking);

        $admin = $this->createMock(AdminInterface::class);
        $event = new PersistenceEvent($admin, $statusEvent, PersistenceEvent::TYPE_PRE_PERSIST);

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->method('getOriginalEntityData')->willReturn(['paid' => false]); // Newly paid

        $this->em->method('getUnitOfWork')->willReturn($unitOfWork);
        $this->em->expects($this->exactly(2))->method('persist'); // Two rewards
        $this->em->expects($this->once())->method('flush');

        $this->subscriber->updateRewards($event);
    }
}
