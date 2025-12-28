<?php

declare(strict_types=1);

namespace App\Tests\Support\Kerde;

use App\Service\ZMQServiceInterface;

/**
 * Fake ZMQ Service for testing.
 *
 * Implements ZMQServiceInterface to provide configurable responses
 * without real ZMQ connections.
 */
final class FakeZMQService implements ZMQServiceInterface
{
    private string $sendResponse = 'ok';
    private string $initResponse = 'connected';
    private string $openResponse = 'door opened';

    public function setSendResponse(string $response): void
    {
        $this->sendResponse = $response;
    }

    public function setInitResponse(string $response): void
    {
        $this->initResponse = $response;
    }

    public function setOpenResponse(string $response): void
    {
        $this->openResponse = $response;
    }

    public function send(string $command): string
    {
        return $this->sendResponse;
    }

    public function sendInit(string $username, int $timestamp): string
    {
        return $this->initResponse;
    }

    public function sendOpen(string $username, int $timestamp): string
    {
        return $this->openResponse;
    }

    public function buildCommand(string $environment, string $action, string $username, int $timestamp): string
    {
        return \sprintf('%s %s: %s %d', $environment, $action, $username, $timestamp);
    }
}
