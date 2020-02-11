<?php

namespace App\EventListener;
use App\Entity\Booking;
use App\Entity\StatusEvent;
use App\Entity\Reward;
use Sonata\AdminBundle\Event\PersistenceEvent;
use Doctrine\ORM\EntityManagerInterface;

class BookingAdminListener
{
    /**
     *
     * @var Swift_Mailer
     */
    private $mailer = null;
    private $email = null;
    private $fromEmail = null;
    private $twig = null;
    private $em = null;

    public function __construct(string $email,string $fromEmail, \Swift_Mailer $mailer, \Twig_Environment $twig, EntityManagerInterface $em)
    {
        $this->email = $email;
        $this->fromEmail = $fromEmail;
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->em = $em;
    }

    public function sendEmailNotification( PersistenceEvent $event )
    {       
        if($this->email){
           $booking = $event->getObject();
           if($booking instanceof Booking){
               $mailer = $this->mailer;
               $message = new \Swift_Message();
               $message->setFrom([$this->fromEmail], "Tunkki");
               $message->setTo($this->email);
               $message->setSubject("[Entropy Tunkki] New Booking on ". $booking->getBookingDate()->format('d.m.Y') );
               $message->setBody(
               $this->twig->render(
                   'emails/notification.html.twig',
                       [
                           'booking' => $booking,
                           'email' => ['addLoginLinksToFooter' => true]
                       ] 
                   ),
                   'text/html'
               );
               $mailer->send($message);
           }
        }
    }
    public function updateRewards(PersistenceEvent $args )
    {
        $event = $args->getObject();
        if ($event instanceof StatusEvent) {
            if($event->getBooking() instanceof Booking){
                $booking = $event->getBooking();
                if($booking->getPaid() && $booking->getActualPrice()>0){ // now it is paid
                    $old = $this->em->getUnitOfWork()->getOriginalEntityData($booking);
                    if(!$old['paid']){ // earlier it was not paid
                        // give reward
                        $amount = $booking->getActualPrice() * 0.10;
                        if ($booking->getGivenAwayBy() == $booking->getReceivedBy()){
                            $gr = $this->giveRewardToUser($amount, $booking, $booking->getGivenAwayBy());
                            $gr->addWeight(2);
                        } else {
                            $gr = $this->giveRewardToUser($amount / 2, $booking, $booking->getGivenAwayBy());
                            $rr = $this->giveRewardToUser($amount / 2, $booking, $booking->getReceivedBy());
                            $gr->addWeight(1);
                            $rr->addWeight(1);
                            $this->em->persist($rr);
                        }
                        $this->em->persist($gr);
                        $this->em->flush();                    
                    }
                }
            }
        }
    }
    private function giveRewardToUser($amount, $booking, $user)
    {
        $all = $user->getRewards();
        foreach($all as $reward){
            if(!$reward->getPaid()){
                $r = $reward;
                break;
            }
        }
        if(!isset($r)){ // doesnt exists
            // create new reward for the user
            $r = new Reward();
            $r->setUser($user);
        }
        if (!$r->getBookings()->contains($booking)){
            $r->addBooking($booking);
        }
        $r->addReward($amount);
        return $r;
    }
}
