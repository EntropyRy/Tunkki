<?php

declare(strict_types=1);

namespace App\Twig\Components\Dashboard;

use App\Entity\Member;
use App\Repository\DoorLogRepository;
use App\Service\ZMQServiceInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class DoorInfo
{
    public string $status = 'Service unavailable';
    public array $logs = [];

    public function __construct(
        private readonly DoorLogRepository $doorLogR,
        private readonly ZMQServiceInterface $zmq,
    ) {
    }

    public function mount(Member $member): void
    {
        $timestamp = new \DateTimeImmutable()->getTimestamp();
        $this->status = $this->zmq->sendInit($member->getUsername() ?? '', $timestamp);
        $this->logs = $this->doorLogR->getLatest(3);
    }
}
