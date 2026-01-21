<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\Rental\Booking\Booking;
use App\Entity\Rental\StatusEvent;
use App\Entity\User;
use App\EventSubscriber\Rental\Booking\BookingAdminSubscriber;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Event\PersistenceEvent;
use Symfony\Component\Mailer\MailerInterface;

/**
 * @covers \App\EventSubscriber\Rental\Booking\BookingAdminSubscriber
 */
final class BookingAdminSubscriberTest extends TestCase
{
    private BookingAdminSubscriber $subscriber;
    private MailerInterface $mailer;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->mailer = $this->createStub(MailerInterface::class);
        $this->em = $this->createStub(EntityManagerInterface::class);
    }

    private function createSubscriber(
        string $adminEmail = 'admin@example.com',
        string $fromEmail = 'from@example.com',
        ?MailerInterface $mailer = null,
        ?EntityManagerInterface $em = null,
    ): BookingAdminSubscriber {
        $this->mailer = $mailer ?? $this->mailer;
        $this->em = $em ?? $this->em;

        return new BookingAdminSubscriber($adminEmail, $fromEmail, $this->mailer, $this->em);
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
        $mailer = $this->createMock(MailerInterface::class);
        $em = $this->createStub(EntityManagerInterface::class);
        $this->subscriber = $this->createSubscriber('', 'from@example.com', $mailer, $em);

        $booking = $this->createStub(Booking::class);
        $admin = $this->createStub(AdminInterface::class);
        $event = new PersistenceEvent($admin, $booking, PersistenceEvent::TYPE_POST_PERSIST);

        $mailer->expects($this->never())->method('send');

        $this->subscriber->sendEmailNotification($event);
    }

    public function testSendEmailNotificationWithZeroEmail(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $em = $this->createStub(EntityManagerInterface::class);
        $this->subscriber = $this->createSubscriber('0', 'from@example.com', $mailer, $em);

        $booking = $this->createStub(Booking::class);
        $admin = $this->createStub(AdminInterface::class);
        $event = new PersistenceEvent($admin, $booking, PersistenceEvent::TYPE_POST_PERSIST);

        $mailer->expects($this->never())->method('send');

        $this->subscriber->sendEmailNotification($event);
    }

    public function testSendEmailNotificationWithNonBookingObject(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $em = $this->createStub(EntityManagerInterface::class);
        $this->subscriber = $this->createSubscriber('admin@example.com', 'from@example.com', $mailer, $em);

        $admin = $this->createStub(AdminInterface::class);
        $event = new PersistenceEvent($admin, new \stdClass(), PersistenceEvent::TYPE_POST_PERSIST);

        $mailer->expects($this->never())->method('send');

        $this->subscriber->sendEmailNotification($event);
    }

    public function testSendEmailNotificationWithValidBooking(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $em = $this->createStub(EntityManagerInterface::class);
        $this->subscriber = $this->createSubscriber('admin@example.com', 'from@example.com', $mailer, $em);

        $booking = $this->createStub(Booking::class);
        $booking->method('getBookingDate')->willReturn(new \DateTimeImmutable('2025-10-18'));

        $admin = $this->createStub(AdminInterface::class);
        $event = new PersistenceEvent($admin, $booking, PersistenceEvent::TYPE_POST_PERSIST);

        $mailer->expects($this->once())->method('send');

        $this->subscriber->sendEmailNotification($event);
    }

    public function testUpdateRewardsWithNonStatusEventObject(): void
    {
        $mailer = $this->createStub(MailerInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $this->subscriber = $this->createSubscriber('admin@example.com', 'from@example.com', $mailer, $em);

        $admin = $this->createStub(AdminInterface::class);
        $event = new PersistenceEvent($admin, new \stdClass(), PersistenceEvent::TYPE_PRE_PERSIST);

        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $this->subscriber->updateRewards($event);
    }

    public function testUpdateRewardsWithStatusEventWithoutBooking(): void
    {
        $mailer = $this->createStub(MailerInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $this->subscriber = $this->createSubscriber('admin@example.com', 'from@example.com', $mailer, $em);

        $statusEvent = $this->createStub(StatusEvent::class);
        $statusEvent->method('getBooking')->willReturn(null);

        $admin = $this->createStub(AdminInterface::class);
        $event = new PersistenceEvent($admin, $statusEvent, PersistenceEvent::TYPE_PRE_PERSIST);

        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $this->subscriber->updateRewards($event);
    }

    public function testUpdateRewardsWithUnpaidBooking(): void
    {
        $mailer = $this->createStub(MailerInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $this->subscriber = $this->createSubscriber('admin@example.com', 'from@example.com', $mailer, $em);

        $booking = $this->createStub(Booking::class);
        $booking->method('getPaid')->willReturn(false);

        $statusEvent = $this->createStub(StatusEvent::class);
        $statusEvent->method('getBooking')->willReturn($booking);

        $admin = $this->createStub(AdminInterface::class);
        $event = new PersistenceEvent($admin, $statusEvent, PersistenceEvent::TYPE_PRE_PERSIST);

        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $this->subscriber->updateRewards($event);
    }

    public function testUpdateRewardsWithPaidBookingZeroPrice(): void
    {
        $mailer = $this->createStub(MailerInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $this->subscriber = $this->createSubscriber('admin@example.com', 'from@example.com', $mailer, $em);

        $booking = $this->createStub(Booking::class);
        $booking->method('getPaid')->willReturn(true);
        $booking->method('getActualPrice')->willReturn('0');

        $statusEvent = $this->createStub(StatusEvent::class);
        $statusEvent->method('getBooking')->willReturn($booking);

        $admin = $this->createStub(AdminInterface::class);
        $event = new PersistenceEvent($admin, $statusEvent, PersistenceEvent::TYPE_PRE_PERSIST);

        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $this->subscriber->updateRewards($event);
    }

    public function testUpdateRewardsWithAlreadyPaidBooking(): void
    {
        $mailer = $this->createStub(MailerInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $this->subscriber = $this->createSubscriber('admin@example.com', 'from@example.com', $mailer, $em);

        $booking = $this->createStub(Booking::class);
        $booking->method('getPaid')->willReturn(true);
        $booking->method('getActualPrice')->willReturn('100');

        $statusEvent = $this->createStub(StatusEvent::class);
        $statusEvent->method('getBooking')->willReturn($booking);

        $admin = $this->createStub(AdminInterface::class);
        $event = new PersistenceEvent($admin, $statusEvent, PersistenceEvent::TYPE_PRE_PERSIST);

        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getOriginalEntityData')->willReturn(['paid' => true]); // Was already paid

        $em->method('getUnitOfWork')->willReturn($unitOfWork);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $this->subscriber->updateRewards($event);
    }

    public function testUpdateRewardsWithNewlyPaidBookingSameGiverReceiver(): void
    {
        $mailer = $this->createStub(MailerInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $this->subscriber = $this->createSubscriber('admin@example.com', 'from@example.com', $mailer, $em);

        $user = $this->createStub(User::class);
        $user->method('getRewards')->willReturn(new ArrayCollection());

        $booking = $this->createStub(Booking::class);
        $booking->method('getPaid')->willReturn(true);
        $booking->method('getActualPrice')->willReturn('100');
        $booking->method('getGivenAwayBy')->willReturn($user);
        $booking->method('getReceivedBy')->willReturn($user);

        $statusEvent = $this->createStub(StatusEvent::class);
        $statusEvent->method('getBooking')->willReturn($booking);

        $admin = $this->createStub(AdminInterface::class);
        $event = new PersistenceEvent($admin, $statusEvent, PersistenceEvent::TYPE_PRE_PERSIST);

        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getOriginalEntityData')->willReturn(['paid' => false]); // Newly paid

        $em->method('getUnitOfWork')->willReturn($unitOfWork);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $this->subscriber->updateRewards($event);
    }

    public function testUpdateRewardsWithNewlyPaidBookingDifferentGiverReceiver(): void
    {
        $mailer = $this->createStub(MailerInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $this->subscriber = $this->createSubscriber('admin@example.com', 'from@example.com', $mailer, $em);

        $givenAwayBy = $this->createStub(User::class);
        $givenAwayBy->method('getRewards')->willReturn(new ArrayCollection());

        $receivedBy = $this->createStub(User::class);
        $receivedBy->method('getRewards')->willReturn(new ArrayCollection());

        $booking = $this->createStub(Booking::class);
        $booking->method('getPaid')->willReturn(true);
        $booking->method('getActualPrice')->willReturn('100');
        $booking->method('getGivenAwayBy')->willReturn($givenAwayBy);
        $booking->method('getReceivedBy')->willReturn($receivedBy);

        $statusEvent = $this->createStub(StatusEvent::class);
        $statusEvent->method('getBooking')->willReturn($booking);

        $admin = $this->createStub(AdminInterface::class);
        $event = new PersistenceEvent($admin, $statusEvent, PersistenceEvent::TYPE_PRE_PERSIST);

        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getOriginalEntityData')->willReturn(['paid' => false]); // Newly paid

        $em->method('getUnitOfWork')->willReturn($unitOfWork);
        $em->expects($this->exactly(2))->method('persist'); // Two rewards
        $em->expects($this->once())->method('flush');

        $this->subscriber->updateRewards($event);
    }
}
