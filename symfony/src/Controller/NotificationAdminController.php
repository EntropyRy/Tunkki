<?php

declare(strict_types=1);

namespace App\Controller;

use Sonata\AdminBundle\Controller\CRUDController;
use Sonata\MediaBundle\Provider\Pool;
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
    public function sendAction(
        ChatterInterface $chatter,
        Request $request,
        Pool $pool,
        TranslatorInterface $ts
    ): RedirectResponse {
        $notification = $this->admin->getSubject();
        $locale = $notification->getLocale();
        $event = $notification->getEvent();
        //$name = $event->getName();
        $picture = $event->getPicture();
        if ($picture !== null) {
            $provider = $pool->getProvider($picture->getProviderName());
            $format = $provider->getFormatName($picture, 'banner');
            $pictureUrl = 'https://entropy.fi'.$provider->generatePublicUrl($picture, $format);
        }
        //dd($pictureUrl);
        $path =  $this->generateUrl('entropy_event_slug', [
            'year' => $event->getEventDate()->format('Y'),
            'slug' => $event->getUrl(),
        ]);
        if ($notification->getLocale() == 'fi') {
            $url = $request->headers->get('host') . $path;
            $nakkikone = $request->headers->get('host') . $path . 'nakkikone';
            $shop = $request->headers->get('host') . $path . 'kauppa';
        } else {
            $url = $request->headers->get('host') . '/en' . $path;
            $nakkikone = $request->headers->get('host') . '/en' . $path . 'nakkikone';
            $shop = $request->headers->get('host') . $path . 'shop';
        }
        $msg = html_entity_decode(strip_tags($notification->getMessage(), '<a><b><strong><u><code><em><a>'));
        $message = new ChatMessage($msg);
        $telegramOptions = (new TelegramOptions());
        if ($notification->getMessageId()) {
            $telegramOptions->edit($notification->getMessageId());
        }
        $telegramOptions
            ->parseMode('HTML')
            ->disableWebPagePreview(true)
            ->disableNotification(true);
        $options = $notification->getOptions();
        $buttons = [];
        foreach ($options as $option) {
            switch ($option) {
                case 'add_event_picture':
                    if ($picture !== null && $notification->getMessageId() == null) {
                        $telegramOptions->photo($pictureUrl);
                    }
                    break;
                case 'add_preview_link':
                    $telegramOptions->disableWebPagePreview(false);
                    break;
                case 'send_notification':
                    $telegramOptions->disableNotification(false);
                    break;
                case 'add_event_button':
                    array_push(
                        $buttons,
                        (new InlineKeyboardButton($ts->trans('tg.event', locale: $locale)))
                            ->url($url)
                    );
                    break;
                case 'add_shop_button':
                    array_push(
                        $buttons,
                        (new InlineKeyboardButton($ts->trans('tg.ticket_shop', locale: $locale)))
                            ->url($shop)
                    );
                    break;
                case 'add_nakkikone_button':
                    array_push(
                        $buttons,
                        (new InlineKeyboardButton($ts->trans('Nakkikone')))
                            ->url($nakkikone)
                    );
                    break;
                default:
                    break;
            }
        }
        // dd($options);
        // dd($buttons);
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
