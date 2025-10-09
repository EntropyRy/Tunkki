<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Notification;
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
        $picture = $event->getPicture();

        // Prepare picture URL
        $publicUrl = null;
        if (null !== $picture) {
            // Get base URL from request
            $scheme = $request->isSecure() ? 'https' : 'http';
            $host = $request->getHost();
            $baseUrl = $scheme.'://'.$host;

            $provider = $pool->getProvider($picture->getProviderName());
            $format = $provider->getFormatName($picture, 'reference');
            $publicUrl = 'https://entropy.fi'.$provider->generatePublicUrl($picture, $format);
        }

        if ('dev' == $_ENV['APP_ENV']) {
            $publicUrl = 'https://entropy.fi/upload/media/event/0001/01/c9ae350d6d50efeadd95eab3270604a78719fb1b.jpg';
        }
        // Prepare event URLs
        $path = '/'.$event->getEventDate()->format('Y').'/'.$event->getUrl();
        $host = 'https://'.$request->headers->get('host');
        if ('fi' == $notification->getLocale()) {
            $nakkikone = $host.$path.'/nakkikone?source=tg';
            $shop = $host.$path.'/kauppa?source=tg';
        } else {
            $nakkikone = $host.'/en'.$path.'/nakkikone?source=tg';
            $shop = $host.'/en'.$path.'/shop?source=tg';
        }

        $url = $host.'/tapahtuma/'.$event->getId().'?source=tg';
        $msg = html_entity_decode(strip_tags((string) $notification->getMessage(), '<a><b><strong><u><code><em><a>'));

        // Prepare buttons with each button on its own row
        $buttonRows = [];
        $options = $notification->getOptions();

        foreach ($options as $option) {
            switch ($option) {
                case 'add_event_button':
                    $buttonRows[] = new InlineKeyboardButton('Tapahtuma / The Event')
                        ->url($url);
                    break;
                case 'add_shop_button':
                    $buttonRows[] = new InlineKeyboardButton($ts->trans('tg.ticket_shop', locale: $locale))
                        ->url($shop);
                    break;
                case 'add_nakkikone_button':
                    $buttonRows[] = new InlineKeyboardButton($ts->trans('Nakkikone'))
                        ->url($nakkikone);
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
                    $telegramOptions->venue((float) $venue->getLatitude(), (float) $venue->getLongitude(), $event->getName().' @ '.$venue->getNameByLocale($locale), $venue->getStreetAddress());
                    break;
                default:
                    break;
            }
        }

        // Handle editing existing messages
        if ($notification->getMessageId()) {
            $telegramOptions->edit($notification->getMessageId());
        }

        // Add the photo if available and requested
        if (null !== $publicUrl && \in_array('add_event_picture', $options) && null === $notification->getMessageId()) {
            $telegramOptions->photo($publicUrl);
        }

        // Add reply markup with buttons if any exist
        if ([] !== $buttonRows) {
            $telegramOptions
                ->replyMarkup(
                    new InlineKeyboardMarkup()
                        ->inlineKeyboard($buttonRows)
                );
            // $markup = new InlineKeyboardMarkup();
            // // Each button gets its own row in the keyboard
            // $markup->inlineKeyboard($buttonRows);
            // $telegramOptions->replyMarkup($markup);
        }

        // Create message
        $message = new ChatMessage($msg);
        $message->options($telegramOptions);

        try {
            // Add debug log before sending
            // error_log('Sending Telegram message with options: ' . json_encode($telegramOptions->toArray()));

            $chatter->send($message);
            //$notification->setMessageId((int) $return->getMessageId());
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
