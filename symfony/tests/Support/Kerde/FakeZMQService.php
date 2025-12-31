<?php

declare(strict_types=1);

namespace App\Tests\Support\Kerde;

use App\DTO\ZmqResponse;
use App\DTO\ZmqStatus;
use App\Service\ZMQServiceInterface;

/**
 * Fake ZMQ Service for testing.
 *
 * Implements ZMQServiceInterface to provide configurable responses
 * without real ZMQ connections.
 */
final class FakeZMQService implements ZMQServiceInterface
{
    private ZmqResponse $sendResponse;
    private ZmqResponse $initResponse;
    private ZmqResponse $openResponse;

    public function __construct()
    {
        $this->sendResponse = ZmqResponse::ok('ok');
        $this->initResponse = ZmqResponse::ok('connected');
        $this->openResponse = ZmqResponse::ok('door opened');
    }

    public function setSendResponse(string $response, ZmqStatus $status = ZmqStatus::OK): void
    {
        $this->sendResponse = new ZmqResponse($status, $response);
    }

    public function setInitResponse(string $response, ZmqStatus $status = ZmqStatus::OK): void
    {
        $this->initResponse = new ZmqResponse($status, $response);
    }

    public function setOpenResponse(string $response, ZmqStatus $status = ZmqStatus::OK): void
    {
        $this->openResponse = new ZmqResponse($status, $response);
    }

    public function send(string $command): ZmqResponse
    {
        return $this->sendResponse;
    }

    public function sendInit(string $username, int $timestamp): ZmqResponse
    {
        return $this->initResponse;
    }

    public function sendOpen(string $username, int $timestamp): ZmqResponse
    {
        return $this->openResponse;
    }

    public function buildCommand(string $environment, string $action, string $username, int $timestamp): string
    {
        return \sprintf('%s %s: %s %d', $environment, $action, $username, $timestamp);
    }
}
