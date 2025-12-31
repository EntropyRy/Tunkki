<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class ZmqResponse
{
    public function __construct(
        public ZmqStatus $status,
        public string $message,
    ) {
    }

    public static function ok(string $message): self
    {
        return new self(ZmqStatus::OK, $message);
    }

    public static function error(string $message): self
    {
        return new self(ZmqStatus::ERROR, $message);
    }

    public static function timeout(string $message): self
    {
        return new self(ZmqStatus::TIMEOUT, $message);
    }

    public function isConnected(): bool
    {
        return ZmqStatus::OK === $this->status;
    }
}
