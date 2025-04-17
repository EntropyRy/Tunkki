<?php

namespace App\Twig\Components\Stream;

use App\Entity\Stream;
use App\Repository\StreamRepository;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\UX\TwigComponent\Attribute\PostMount;

#[AsLiveComponent]
final class Player
{
    use DefaultActionTrait;
    use ComponentToolsTrait;

    public const string FORMAT_MP3 = 'mp3';
    public const string FORMAT_OPUS = 'opus';

    #[LiveProp]
    public string $url = 'https://stream.entropy.fi';

    #[LiveProp]
    public string $onlineImg = '/images/entropy-stream-online.svg';

    #[LiveProp]
    public string $offlineImg = '/images/entropy-stream-offline.svg';

    #[LiveProp(writable: true)]
    public ?Stream $stream = null;

    #[LiveProp(writable: true)]
    public string $currentImg;

    #[LiveProp(writable: true)]
    public string $badgeText = 'stream.loading';

    #[LiveProp(writable: true)]
    public bool $isOnline = false;

    #[LiveProp(writable: true)]
    public int $listeners = 0;

    #[LiveProp(writable: true)]
    public bool $showPlayer = false;

    #[LiveProp(writable: true)]
    public string $streamFormat = self::FORMAT_MP3;

    #[LiveProp]
    public int $refreshInterval = 20000;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly StreamRepository $streamRepository
    ) {
        // Initialize the current image to the offline image
        $this->currentImg = $this->offlineImg;
    }

    #[PostMount]
    public function postMount(): void
    {
        $this->checkStreamStatus();
    }

    #[LiveAction]
    public function checkStreamStatus(): void
    {
        $this->stream = $this->streamRepository->findOneBy(['online' => true], ['id' => 'DESC']);
        if ($this->stream === null) {
            $this->setOfflineState();
            return;
        } else {
            $this->setOnlineState();
        }
        try {
            // Get the main page which contains information about both streams
            $response = $this->httpClient->request('GET', $this->url, [
                'verify_peer' => false,
                'verify_host' => false,
                'timeout' => 5.0,
            ]);

            if ($response->getStatusCode() === 200) {
                $content = $response->getContent();

                // Check if there are any mount points by looking for the mount class
                $hasMountPoints = str_contains($content, 'class="mount"');

                if ($hasMountPoints) {
                    // Count total listeners from all streams
                    $this->listeners = 0;
                    if (preg_match_all('/<listeners>(\d+)<\/listeners>/', $content, $matches)) {
                        foreach ($matches[1] as $count) {
                            $this->listeners += (int) $count;
                        }
                    }
                    $this->badgeText = 'ONLINE: ' . $this->listeners;
                    $this->updateStreamListeners($this->listeners);
                    $this->showPlayer = true;
                }
            }
        } catch (\Exception $e) {
            // Log the error but don't break the component
            error_log('StreamPlayer listener update error: ' . $e->getMessage());
            /* dd($e->getMessage()); */
            /* $this->setOfflineState(); */
        }
    }

    private function setOfflineState(): void
    {
        // Set the stream to offline stated
        if ($this->isOnline == false) {
            return;
        }
        $this->stream = null;
        $this->isOnline = false;
        $this->listeners = 0;
        $this->badgeText = 'OFFLINE';
        $this->currentImg = $this->offlineImg;
        $this->showPlayer = false;
        $this->refreshInterval = 20000; // Reset refresh interval to default
        $this->emit('stream:stopped');
    }

    private function setOnlineState(): void
    {
        // Set the stream to online state
        if ($this->isOnline == true) {
            return;
        }
        $this->isOnline = true;
        $this->currentImg = $this->onlineImg;
        $this->refreshInterval = 10000;
        $this->emit('stream:started');
    }

    private function updateStreamListeners(int $listeners): void
    {
        // $this->stream = $this->streamRepository->findOneBy([], ['id' => 'DESC']);
        if ($this->stream === null) {
            return;
        }
        $old = $this->stream->getListeners();
        if ($old < $listeners) {
            $this->stream->setListeners($listeners);
            $this->streamRepository->save($this->stream, true);
        }
        $this->emit('stream:updated');
    }

    #[LiveAction]
    public function setStreamFormat(#[LiveArg] string $format): void
    {
        if (in_array($format, [self::FORMAT_MP3, self::FORMAT_OPUS])) {
            $this->streamFormat = $format;
        }
    }

    public function getStreamUrl(): string
    {
        return $this->url . '/kerde.' . $this->streamFormat;
    }

    public function getStreamMimeType(): string
    {
        return $this->streamFormat === self::FORMAT_MP3 ? 'audio/mpeg' : 'application/ogg';
    }
}
