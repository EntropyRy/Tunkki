<?php

declare(strict_types=1);

namespace App\Controller;

use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Bridge\Telegram\Reply\Markup\Button\InlineKeyboardButton;
use Symfony\Component\Notifier\Bridge\Telegram\Reply\Markup\InlineKeyboardMarkup;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramOptions;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class NotificationAdminController extends CRUDController
{
    public function sendAction(ChatterInterface $chatter, Request $request, TranslatorInterface $ts): RedirectResponse
    {
        $notification = $this->admin->getSubject();
        $locale = $notification->getLocale();
        $event = $notification->getEvent();
        //$name = $event->getName();
        $path =  $this->generateUrl('entropy_event_slug', [
            'year' => $event->getEventDate()->format('Y'),
            'slug' => $event->getUrl(),
        ]);
        if ($notification->getLocale() == 'fi') {
            $url = $request->headers->get('host') . $path;
            $nakkikone = $request->headers->get('host') . $path . 'nakkikone';
        } else {
            $url = $request->headers->get('host') . '/en' . $path;
            $nakkikone = $request->headers->get('host') . '/en' . $path . 'nakkikone';
        }
        $message = new ChatMessage(
            $notification->getMessage() . ' 
' . $url
        );
        if ($notification->getMessageId()) {
            $telegramOptions = (new TelegramOptions())
                ->edit($notification->getMessageId())
                ->parseMode('MarkdownV2')
                ->disableWebPagePreview(false)
                ->disableNotification(false);
        } else {
            $telegramOptions = (new TelegramOptions())
                ->parseMode('MarkdownV2')
                ->disableWebPagePreview(false)
                ->disableNotification(false);
        }
        $options = $notification->getOptions();
        $buttons = [];
        if (in_array('add_event_button', $options)) {
            array_push(
                $buttons,
                (new InlineKeyboardButton($ts->trans('tg.event', locale: $locale)))
                    ->url($url)
            );
        }
        if (in_array('add_nakkikone_button', $options)) {
            array_push(
                $buttons,
                (new InlineKeyboardButton($ts->trans('Nakkikone')))
                    ->url($nakkikone)
            );
        }
        //dd($buttons);
        if (!empty($buttons)) {
            $telegramOptions
                ->replyMarkup(
                    (new InlineKeyboardMarkup())
                        ->inlineKeyboard($buttons)
                );
        }
        $message->options($telegramOptions);
        try {
            $return = $chatter->send($message);
            $notification->setMessageId((int) $return->getMessageId());
            $notification->setSentAt(new \DateTimeImmutable('now'));
            $this->admin->update($notification);
            $this->addFlash('success', 'Message sent');
        } catch (\Exception $e) {
            $this->addFlash('warning', 'Message NOT sent: ' . $e->getMessage());
        }
        return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
    }
}
