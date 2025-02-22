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
        $path = '/'. $event->getEventDate()->format('Y') . '/' . $event->getUrl();
        $host = $request->headers->get('host');
        if ($notification->getLocale() == 'fi') {
            $nakkikone = $host . $path . '/nakkikone?source=tg';
            $shop = $host . $path . '/kauppa?source=tg';
        } else {
            $nakkikone = $host . '/en' . $path . '/nakkikone?source=tg';
            $shop = $host . '/en'. $path . '/shop?source=tg';
        }
        $url = $host . '/tapahtuma/'.$event->getId().'?source=tg';
        $msg = html_entity_decode(strip_tags((string) $notification->getMessage(), '<a><b><strong><u><code><em><a>'));
        $message = new ChatMessage($msg);
        $telegramOptions = (new TelegramOptions())
            ->parseMode('HTML')
            ->disableWebPagePreview(true)
            ->disableNotification(true);
        if ($notification->getMessageId()) {
            $telegramOptions->edit($notification->getMessageId());
        }
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
                case 'add_venue':
                    $venue = $event->getLocation();
                    $telegramOptions->venue((float)$venue->getLatitude(), (float)$venue->getLongitude(), $venue->getName(), $venue->getStreetAddress());
                    break;
                case 'add_event_button':
                    $buttons[] = (new InlineKeyboardButton('Tapahtuma / The Event'))
                        ->url($url);
                    break;
                case 'add_shop_button':
                    $buttons[] = (new InlineKeyboardButton($ts->trans('tg.ticket_shop', locale: $locale)))
                        ->url($shop);
                    break;
                case 'add_nakkikone_button':
                    $buttons[] = (new InlineKeyboardButton($ts->trans('Nakkikone')))
                        ->url($nakkikone);
                    break;
                default:
                    break;
            }
        }
        if ($buttons !== []) {
            $telegramOptions
                ->replyMarkup(
                    (new InlineKeyboardMarkup())
                        ->inlineKeyboard($buttons)
                );
        }
        //dd($telegramOptions);
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
