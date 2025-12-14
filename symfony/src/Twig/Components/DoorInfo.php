<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Member;
use App\Repository\DoorLogRepository;
use App\Service\ZMQService;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class DoorInfo
{
    public string $status = 'Service unavailable';
    public array $logs = [];

    public function __construct(
        private readonly DoorLogRepository $doorLogR,
        private readonly ZMQService $zmq,
    ) {
    }

    public function mount(Member $member): void
    {
        if (null === $member) {
            return;
        }
        $timestamp = (new \DateTimeImmutable())->getTimestamp();
        $this->status = $this->zmq->sendInit($member, $timestamp);
        $this->logs = $this->doorLogR->getLatest(3);
    }
}
