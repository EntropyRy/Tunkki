<?php

namespace App\EventListener;

use App\Entity\Booking;
use App\Entity\StatusEvent;
use App\Entity\Reward;
use Sonata\AdminBundle\Event\PersistenceEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class BookingAdminListener implements EventSubscriberInterface
{
    public function __construct(private readonly string $email, private readonly string $fromEmail, private readonly MailerInterface $mailer, private readonly EntityManagerInterface $em)
    {
    }
    /**
     * @param PersistenceEvent<object> $event
     */
    public function sendEmailNotification(PersistenceEvent $event): void
    {
        if ($this->email !== '' && $this->email !== '0') {
            $booking = $event->getObject();
            if ($booking instanceof Booking) {
                $mailer = $this->mailer;
                $email = (new TemplatedEmail())
                    ->from(new Address($this->fromEmail, 'Tunkki'))
                    ->to($this->email)
                    ->subject("[Entropy Tunkki] New Booking on " . $booking->getBookingDate()->format('d.m.Y'))
                    ->htmlTemplate('emails/notification.html.twig')
                    ->context([
                        'booking' => $booking,
                        'links' => true
                    ]);
                $mailer->send($email);
            }
        }
    }
    /**
     * @param PersistenceEvent<object> $args
     */
    public function updateRewards(PersistenceEvent $args): void
    {
        $event = $args->getObject();
        if ($event instanceof StatusEvent && $event->getBooking() instanceof Booking) {
            $booking = $event->getBooking();
            if ($booking->getPaid() && $booking->getActualPrice() > 0) { // now it is paid
                $old = $this->em->getUnitOfWork()->getOriginalEntityData($booking);
                // earlier it was not paid
                // give reward
                if (!$old['paid'] && !empty($booking->getActualPrice())) {
                    $amount = (float) $booking->getActualPrice() * 0.10;
                    if ($booking->getGivenAwayBy() === $booking->getReceivedBy()) {
                        $gr = $this->giveRewardToUser($amount, $booking, $booking->getGivenAwayBy());
                        $gr->addWeight(2);
                        $this->em->persist($gr);
                    } else {
                        $gr = $this->giveRewardToUser($amount / 2, $booking, $booking->getGivenAwayBy());
                        $gr->addWeight(1);
                        $this->em->persist($gr);
                        $rr = $this->giveRewardToUser($amount / 2, $booking, $booking->getReceivedBy());
                        $rr->addWeight(1);
                        $this->em->persist($rr);
                    }
                    $this->em->flush();
                }
            }
        }
    }
    private function giveRewardToUser(mixed $amount, mixed $booking, mixed $user): Reward
    {
        $all = $user->getRewards();
        foreach ($all as $reward) {
            if (!$reward->getPaid()) {
                $r = $reward;
                break;
            }
        }
        if (!isset($r)) { // doesnt exists
            // create new reward for the user
            $r = new Reward();
            $r->setUser($user);
        }
        if (!$r->getBookings()->contains($booking)) {
            $r->addBooking($booking);
        }
        $r->addReward($amount);
        return $r;
    }
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            'sonata.admin.event.persistence.post_persist' => 'sendEmailNotification',
            'sonata.admin.event.persistence.pre_persist' => 'updateRewards'
        ];
    }
}
