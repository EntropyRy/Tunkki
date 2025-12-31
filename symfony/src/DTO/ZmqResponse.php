<?php

declare(strict_types=1);

namespace App\DTO;

final class ZmqResponse
{
    public function __construct(
        public readonly ZmqStatus $status,
        public readonly string $message,
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
