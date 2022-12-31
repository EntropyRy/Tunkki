<?php

namespace App\EventListener;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class LoginListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {
    }
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $request = $event->getRequest();
        $user->setLastLogin(new \DateTime());
        if (!is_null($user->getMember()->getLocale())) {
            $request->setLocale($user->getMember()->getLocale());
        } else {
            $user->getMember()->setLocale('fi');
            $request->setLocale('fi');
        }
        $this->em->persist($user);
        $this->em->flush();
    }
    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess'
        ];
    }
}
