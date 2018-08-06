<?php

namespace Entropy\TunkkiBundle\EventListener;

class BookingAdminListener
{
    /**
     *
     * @var Swift_Mailer
     */
    private $mailer = null;
    private $templating = null;
    private $email = null;
    private $img = null;

    public function __construct(\Swift_Mailer $mailer, $templating, $email, $img)
    {
        $this->mailer = $mailer;
        $this->templating = $templating;
        $this->email = $email;
        $this->img = $img;
    }

    public function sendEmailNotification( \Sonata\AdminBundle\Event\PersistenceEvent $event )
    {       
        if($this->email){
           $booking = $event->getObject();
           //$results = [];
           $mailer = $this->mailer;
           $message = new \Swift_Message();
           $message->setFrom([$this->email], "Tunkki");
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
           //$results[] = 
           $mailer->send($message);
        }
    }
}
