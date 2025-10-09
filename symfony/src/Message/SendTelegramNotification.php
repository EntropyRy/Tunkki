<?php

declare(strict_types=1);

namespace App\Message;

use App\Entity\Notification;

final readonly class SendTelegramNotification
{
    public function __construct(
        private int $notificationId,
    ) {
    }

    public function getNotificationId(): int
    {
        return $this->notificationId;
    }
}
