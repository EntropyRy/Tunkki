<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\ZmqResponse;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * ZMQ Service for sending commands to the door control system via ZeroMQ.
 *
 * Provides:
 * - ZMQ socket connection management with configurable timeouts
 * - Command building utilities for door control operations
 * - Error handling for socket exceptions
 * - Abstraction over low-level ZMQ socket operations
 *
 * Command format: "{environment} {action}: {username} {timestamp}"
 * Examples:
 *   - "dev init: john_doe 1697654321"
 *   - "prod open: jane_smith 1697654322"
 */
class ZMQService implements ZMQServiceInterface
{
    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly string $environment = 'prod',
        private readonly int $receiveTimeout = 1000,
        private readonly int $lingerTimeout = 2000,
    ) {
    }

    /**
     * Send a raw command string to the door control system.
     *
     * @param string $command The command string to send
     *
     * @return ZmqResponse Response from the door system with status and message
     */
    public function send(string $command): ZmqResponse
    {
        try {
            $socket = $this->connect();
            $socket->send($command);
            $response = $socket->recv();
        } catch (\Throwable $e) {
            return ZmqResponse::error($e->getMessage());
        }

        if (empty($response)) {
            return ZmqResponse::timeout('broken');
        }

        return ZmqResponse::ok($response);
    }

    /**
     * Send an "init" command (initial connection check) using the injected environment.
     *
     * @param string $username  Username performing the action
     * @param int    $timestamp Unix timestamp of the action
     *
     * @return ZmqResponse Response from the door system
     */
    public function sendInit(string $username, int $timestamp): ZmqResponse
    {
        $command = $this->buildCommand($this->environment, 'init', $username, $timestamp);

        return $this->send($command);
    }

    /**
     * Send an "open" command to open the door using the injected environment.
     *
     * @param string $username  Username performing the action
     * @param int    $timestamp Unix timestamp of the action
     *
     * @return ZmqResponse Response from the door system
     */
    public function sendOpen(string $username, int $timestamp): ZmqResponse
    {
        $command = $this->buildCommand($this->environment, 'open', $username, $timestamp);

        return $this->send($command);
    }

    /**
     * Build a command string in the expected format.
     *
     * @param string $environment Environment name ('dev' or 'prod')
     * @param string $action      Action name ('init', 'open', etc.)
     * @param string $username    Username performing the action
     * @param int    $timestamp   Unix timestamp of the action
     */
    public function buildCommand(string $environment, string $action, string $username, int $timestamp): string
    {
        return \sprintf('%s %s: %s %d', $environment, $action, $username, $timestamp);
    }

    /**
     * Establish a ZMQ socket connection to the door control system.
     *
     * @return \ZMQSocket Configured ZMQ socket ready for communication
     *
     * @throws \ZMQSocketException on connection failure
     */
    /**
     * @return object ZMQ socket-like object supporting send()/recv()
     */
    protected function connect(): object
    {
        $doorSocket = (string) $this->parameterBag->get('door_socket');

        $context = new \ZMQContext();
        $socket = $context->getSocket(\ZMQ::SOCKET_REQ);
        $socket->setSockOpt(\ZMQ::SOCKOPT_RCVTIMEO, $this->receiveTimeout);
        $socket->setSockOpt(\ZMQ::SOCKOPT_LINGER, $this->lingerTimeout);
        $socket->connect($doorSocket);

        return $socket;
    }
}
