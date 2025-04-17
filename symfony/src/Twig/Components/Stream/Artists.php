<?php

namespace App\Twig\Components\Stream;

use App\Entity\Stream;
use App\Repository\StreamRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\Attribute\LiveProp;

#[AsLiveComponent]
final class Artists extends AbstractController
{
    use DefaultActionTrait;
    use ComponentToolsTrait;

    #[LiveProp]
    public ?Stream $stream = null;

    #[LiveProp]
    public bool $isOnline = false;

    #[LiveProp]
    public string $hash = '';

    public function __construct(private readonly StreamRepository $streamRepository)
    {
    }

    public function mount(): void
    {
        $stream = $this->streamRepository->findOneBy(['online' => true], ['id' => 'DESC']);
        if ($stream) {
            $this->stream = $stream;
            $this->isOnline = true;
        }
    }

    #[LiveListener('stream:updated')]
    public function onStreamUpdated(): void
    {
        $stream = $this->streamRepository->findOneBy(['online' => true], ['id' => 'DESC']);
        if ($stream) {
            $this->stream = $stream;
            $this->isOnline = true;
            $this->hash = $stream->getUpdatedAt()->format('U');
        } else {
            $this->stream = null;
            $this->isOnline = false;
            $this->hash = '';
        }
    }
}
