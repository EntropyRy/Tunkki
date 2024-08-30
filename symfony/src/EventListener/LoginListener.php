<?php

namespace App\EventListener;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class LoginListener implements EventSubscriberInterface
{
    public function __construct(
        private LocaleSwitcher $localeSwitcher,
        private readonly EntityManagerInterface $em
    ) {
    }
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        // get user
        $user = $event->getUser();
        assert($user instanceof User);
        $user->setLastLogin(new \DateTime());
        $this->em->persist($user);
        $this->em->flush();
        // set user locale in session
        $userLocale = $user->getMember()->getLocale() ?? 'fi';
        $this->localeSwitcher->setLocale($userLocale);
    }
    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }
}
