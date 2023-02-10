<?php

declare(strict_types=1);

namespace App\Controller;

use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Bridge\Telegram\Reply\Markup\Button\InlineKeyboardButton;
use Symfony\Component\Notifier\Bridge\Telegram\Reply\Markup\InlineKeyboardMarkup;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramOptions;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class EventAdminController extends CRUDController
{
    public function artistListAction(): Response
    {
        $event = $this->admin->getSubject();
        $infos = $event->getEventArtistInfos();
        return $this->renderWithExtraParams('admin/event/artist_list.html.twig', [
            'event' => $event,
            'infos' => $infos
        ]);
    }
    public function nakkiListAction(): Response
    {
        $event = $this->admin->getSubject();
        $nakkis = $event->getNakkiBookings();
        $emails = [];
        foreach ($nakkis as $nakki) {
            $member = $nakki->getMember();
            if ($member) {
                $emails[$member->getId()] = $member->getEmail();
            }
        }
        $emails = implode(';', $emails);
        return $this->renderWithExtraParams('admin/event/nakki_list.html.twig', [
            'event' => $event,
            'nakkiBookings' => $nakkis,
            'emails' => $emails
        ]);
    }
    public function rsvpAction(): Response
    {
        $event = $this->admin->getSubject();
        $rsvps = $event->getRSVPs();
        //$email_url = $this->admin->generateUrl('rsvpEmail', ['id' => $event->getId()]);
        return $this->renderWithExtraParams('admin/event/rsvps.html.twig', [
            'event' => $event,
            'rsvps' => $rsvps,
            //'email_url' => $email_url
        ]);
    }
    public function tgAction(ChatterInterface $chatter): RedirectResponse
    {
        $event = $this->admin->getSubject();
        $name = $event->getName();
        $url =  $this->generateUrl('entropy_event_slug', [
            'year' => $event->getEventDate()->format('Y'),
            'slug' => $event->getUrl()
        ], UrlGeneratorInterface::ABSOLUTE_URL);
        $message = new ChatMessage(
            'Event ' . $name . ' Updated: ' .
                $url
        );

        $telegramOptions = (new TelegramOptions())
            //->chatId('-801641481')
            ->parseMode('MarkdownV2')
            ->disableWebPagePreview(false)
            ->disableNotification(false)
            ->replyMarkup(
                (new InlineKeyboardMarkup())
                    ->inlineKeyboard([
                        (new InlineKeyboardButton('Check it out!'))
                            ->url($url),
                    ])
            );
        $message->options($telegramOptions);
        $chatter->send($message);
        $this->addFlash('success', 'Message sent');
        return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
    }
}
