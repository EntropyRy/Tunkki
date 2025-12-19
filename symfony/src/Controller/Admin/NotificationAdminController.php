<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Event;
use App\Entity\Location;
use App\Entity\Notification;
use App\Entity\Sonata\SonataMediaMedia;
use Sonata\AdminBundle\Controller\CRUDController;
use Sonata\MediaBundle\Provider\Pool;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Notifier\Bridge\Telegram\Reply\Markup\Button\InlineKeyboardButton;
use Symfony\Component\Notifier\Bridge\Telegram\Reply\Markup\InlineKeyboardMarkup;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramOptions;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends CRUDController<Notification>
 */
final class NotificationAdminController extends CRUDController
{
    public function sendAction(
        ChatterInterface $chatter,
        Request $request,
        Pool $pool,
        TranslatorInterface $ts,
    ): RedirectResponse {
        $notification = $this->admin->getSubject();
        $locale = $notification->getLocale();
        $event = $notification->getEvent();
        if (!$event instanceof Event) {
            return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
        }
        $picture = $event->getPicture();
        $baseUrl = $request->getSchemeAndHttpHost();
        // Prepare picture URL
        $publicUrl = null;
        if ($picture instanceof SonataMediaMedia) {
            $provider = $pool->getProvider($picture->getProviderName());
            $format = $provider->getFormatName($picture, 'reference');
            $publicUrl = $baseUrl.$provider->generatePublicUrl($picture, $format);
        }

        if (($_ENV['APP_ENV'] ?? null) === 'dev') {
            $publicUrl = 'https://entropy.fi/upload/media/event/0001/01/c9ae350d6d50efeadd95eab3270604a78719fb1b.jpg';
        }
        // Prepare event URLs
        $path = '/'.$event->getEventDate()->format('Y').'/'.$event->getUrl();
        if ('fi' === $locale) {
            $nakkikone = $baseUrl.$path.'/nakkikone?source=tg';
            $shop = $baseUrl.$path.'/kauppa?source=tg';
            $eventUrl = $baseUrl.'/tapahtuma/'.$event->getId().'?source=tg';
        } else {
            $nakkikone = $baseUrl.'/en'.$path.'/nakkikone?source=tg';
            $shop = $baseUrl.'/en'.$path.'/shop?source=tg';
            $eventUrl = $baseUrl.'/en/event/'.$event->getId().'?source=tg';
        }

        $rawMessage = $notification->getMessage() ?? '';
        $msg = html_entity_decode(strip_tags($rawMessage, '<a><b><strong><u><code><em>'));

        // Prepare buttons with each button on its own row
        $options = $notification->getOptions();
        $buttonsOnePerRow = \in_array('buttons_one_per_row', $options, true);
        $buttons = [];

        foreach ($options as $option) {
            switch ($option) {
                case 'add_event_button':
                    $buttons[] = new InlineKeyboardButton('Tapahtuma / The Event')->url($eventUrl);
                    break;
                case 'add_shop_button':
                    $buttons[] = new InlineKeyboardButton($ts->trans('tg.ticket_shop', locale: $locale))->url($shop);
                    break;
                case 'add_nakkikone_button':
                    $buttons[] = new InlineKeyboardButton($ts->trans('Nakkikone', locale: $locale))->url($nakkikone);
                    break;
                default:
                    break;
            }
        }

        // Create Telegram options
        $telegramOptions = new TelegramOptions()
            ->parseMode('HTML')
            ->disableWebPagePreview(true)
            ->disableNotification(true);

        // Apply specific options
        foreach ($options as $option) {
            switch ($option) {
                case 'add_preview_link':
                    $telegramOptions->disableWebPagePreview(false);
                    break;
                case 'send_notification':
                    $telegramOptions->disableNotification(false);
                    break;
                case 'add_venue':
                    $venue = $event->getLocation();
                    if (!$venue instanceof Location) {
                        $this->addFlash('warning', 'Cannot add venue: event has no location.');

                        return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
                    }
                    $telegramOptions->venue((float) $venue->getLatitude(), (float) $venue->getLongitude(), $event->getName().' @ '.$venue->getNameByLocale($locale), $venue->getStreetAddress());
                    break;
                default:
                    break;
            }
        }

        // Add the photo if available and requested
        if (null !== $publicUrl && \in_array('add_event_picture', $options, true) && null === $notification->getMessageId()) {
            $telegramOptions->photo($publicUrl);
        }

        // Add reply markup with buttons if any exist
        if ([] !== $buttons) {
            $markup = new InlineKeyboardMarkup();
            if ($buttonsOnePerRow) {
                foreach ($buttons as $button) {
                    $markup->inlineKeyboard([$button]);
                }
            } else {
                $markup->inlineKeyboard($buttons);
            }

            $telegramOptions->replyMarkup($markup);
        }

        // Create message
        $message = new ChatMessage($msg);
        $message->options($telegramOptions);

        try {
            // Add debug log before sending
            // error_log('Sending Telegram message with options: ' . json_encode($telegramOptions->toArray()));

            $chatter->send($message);
            // $notification->setMessageId((int) $return->getMessageId());
            $notification->setSentAt(new \DateTimeImmutable('now'));
            $this->admin->update($notification);
            $this->addFlash('success', 'Message sent');
        } catch (\Exception $e) {
            $this->addFlash('warning', 'Message NOT sent: '.$e->getMessage());
            // error_log('Telegram error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        }

        return new RedirectResponse($this->admin->generateUrl('list', $this->admin->getFilterParameters()));
    }
}
