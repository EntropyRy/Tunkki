<?php

namespace App\EventListener;

use Symfony\Component\HttpFoundation\Session\Session;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class LoginListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly LocaleSwitcher $localeSwitcher,
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $urlGenerator,
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
        $userLocale = $user->getMember()->getLocale() ?? "fi";
        $this->localeSwitcher->setLocale($userLocale);

        // flash notice if email not verified
        $member = $user->getMember();
        if ($member && !$member->isEmailVerified()) {
            $request = $event->getRequest();
            if ($request->hasSession()) {
                $resendUrl = $this->urlGenerator->generate(
                    "profile_resend_verification",
                    ["_locale" => $userLocale],
                );
                if ($userLocale === "fi") {
                    $message =
                        'Sähköpostiosoitteesi ei ole vahvistettu. <a href="' .
                        $resendUrl .
                        '">Lähetä vahvistussähköposti uudelleen</a>.';
                } else {
                    $message =
                        'Your email address is not verified. <a href="' .
                        $resendUrl .
                        '">Resend verification email</a>.';
                }
                $session = $request->getSession();
                assert($session instanceof Session);
                $session->getFlashBag()->add("warning", $message);
            }
        }
    }
    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => "onLoginSuccess",
        ];
    }
}
