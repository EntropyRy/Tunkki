<?php

namespace App\EventListener;

class BookingAdminListener
{
    /**
     *
     * @var Swift_Mailer
     */
    private $mailer = null;
    private $templating = null;
    private $email = null;
    private $fromEmail = null;
    private $img = null;

    public function __construct(\Swift_Mailer $mailer, $templating, $email, $img,$fromEmail)
    {
        $this->mailer = $mailer;
        $this->templating = $templating;
        $this->email = $email;
        $this->img = $img;
        $this->fromEmail = $fromEmail;
    }

    public function sendEmailNotification( \Sonata\AdminBundle\Event\PersistenceEvent $event )
    {       
        if($this->email){
           if(get_class($event->getObject()) == "Entropy\TunkkiBundle\Entity\Booking"){
               $booking = $event->getObject();
               $mailer = $this->mailer;
               $message = new \Swift_Message();
               $message->setFrom([$this->fromEmail], "Tunkki");
               $message->setTo($this->email);
               $message->setSubject("[Entropy Tunkki] New Booking on ". $booking->getBookingDate()->format('d.m.Y') );
               $message->setBody(
               $this->templating->render(
                   'EntropyTunkkiBundle:Emails:notification.html.twig',
                       [
                           'img' => $this->img,
                           'booking' => $booking,
                       ] 
                   ),
                   'text/html'
               );
               $mailer->send($message);
           }
        }
    }
}
