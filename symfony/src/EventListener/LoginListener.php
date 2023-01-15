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
        assert($user instanceof User);
        $request = $event->getRequest();
        $user->setLastLogin(new \DateTime());
        $request->setLocale($user->getMember()->getLocale());
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
