<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Notification;
use App\Message\SendTelegramNotification;
use Doctrine\ORM\EntityManagerInterface;
use Sonata\MediaBundle\Provider\Pool;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Notifier\Bridge\Telegram\Reply\Markup\Button\InlineKeyboardButton;
use Symfony\Component\Notifier\Bridge\Telegram\Reply\Markup\InlineKeyboardMarkup;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramOptions;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class SendTelegramNotificationHandler
{
    public function __construct(
        private ChatterInterface $chatter,
        private EntityManagerInterface $entityManager,
        private Pool $mediaPool,
        private RequestStack $requestStack,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(SendTelegramNotification $message): void
    {
        $notification = $this->entityManager->getRepository(Notification::class)->find($message->getNotificationId());

        if (null === $notification) {
            throw new \RuntimeException(sprintf('Notification with ID %d not found', $message->getNotificationId()));
        }

        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            throw new \RuntimeException('No current request available');
        }

        $locale = $notification->getLocale();
        $event = $notification->getEvent();
        $picture = $event->getPicture();

        // Prepare picture URL
        $publicUrl = null;
        if (null !== $picture) {
            $provider = $this->mediaPool->getProvider($picture->getProviderName());
            $format = $provider->getFormatName($picture, 'reference');
            $publicUrl = 'https://entropy.fi'.$provider->generatePublicUrl($picture, $format);
        }

        if ('dev' === $_ENV['APP_ENV']) {
            $publicUrl = 'https://entropy.fi/upload/media/event/0001/01/c9ae350d6d50efeadd95eab3270604a78719fb1b.jpg';
        }

        // Prepare event URLs
        $path = '/'.$event->getEventDate()->format('Y').'/'.$event->getUrl();
        $host = 'https://'.$request->headers->get('host');
        if ('fi' === $notification->getLocale()) {
            $nakkikone = $host.$path.'/nakkikone?source=tg';
            $shop = $host.$path.'/kauppa?source=tg';
        } else {
            $nakkikone = $host.'/en'.$path.'/nakkikone?source=tg';
            $shop = $host.'/en'.$path.'/shop?source=tg';
        }

        $url = $host.'/tapahtuma/'.$event->getId().'?source=tg';
        $msg = html_entity_decode(strip_tags((string) $notification->getMessage(), '<a><b><strong><u><code><em><a>'));

        // Prepare buttons
        $buttonRows = [];
        $options = $notification->getOptions();

        foreach ($options as $option) {
            switch ($option) {
                case 'add_event_button':
                    $buttonRows[] = new InlineKeyboardButton('Tapahtuma / The Event')
                        ->url($url);
                    break;
                case 'add_shop_button':
                    $buttonRows[] = new InlineKeyboardButton($this->translator->trans('tg.ticket_shop', locale: $locale))
                        ->url($shop);
                    break;
                case 'add_nakkikone_button':
                    $buttonRows[] = new InlineKeyboardButton($this->translator->trans('Nakkikone'))
                        ->url($nakkikone);
                    break;
            }
        }

        // Create Telegram options
        $telegramOptions = (new TelegramOptions())
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
                    $telegramOptions->venue(
                        (float) $venue->getLatitude(),
                        (float) $venue->getLongitude(),
                        $event->getName().' @ '.$venue->getNameByLocale($locale),
                        $venue->getStreetAddress()
                    );
                    break;
            }
        }

        // Handle editing existing messages
        if ($notification->getMessageId()) {
            $telegramOptions->edit($notification->getMessageId());
        }

        // Add photo if available and requested
        if (null !== $publicUrl && \in_array('add_event_picture', $options, true) && null === $notification->getMessageId()) {
            $telegramOptions->photo($publicUrl);
        }

        // Add reply markup with buttons
        if ([] !== $buttonRows) {
            $telegramOptions->replyMarkup(
                (new InlineKeyboardMarkup())->inlineKeyboard($buttonRows)
            );
        }

        // Create and send message
        $chatMessage = new ChatMessage($msg);
        $chatMessage->options($telegramOptions);

        $sentMessage = $this->chatter->send($chatMessage);

        // Update notification with message ID and sent timestamp
        $notification->setMessageId((int) $sentMessage->getMessageId());
        $notification->setSentAt(new \DateTimeImmutable('now'));
        $this->entityManager->flush();
    }
}
