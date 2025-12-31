<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Interface for ZMQ door control service.
 *
 * Provides methods for:
 * - Sending raw commands to the door control system
 * - Sending init/open commands with user tracking
 * - Building command strings
 */
interface ZMQServiceInterface
{
    /**
     * Send a raw command string to the door control system.
     *
     * @param string $command The command string to send
     *
     * @return \App\DTO\ZmqResponse Response from the door system with status and message
     */
    public function send(string $command): \App\DTO\ZmqResponse;

    /**
     * Send an "init" command (initial connection check).
     *
     * @param string $username  Username performing the action
     * @param int    $timestamp Unix timestamp of the action
     *
     * @return \App\DTO\ZmqResponse Response from the door system
     */
    public function sendInit(string $username, int $timestamp): \App\DTO\ZmqResponse;

    /**
     * Send an "open" command to open the door.
     *
     * @param string $username  Username performing the action
     * @param int    $timestamp Unix timestamp of the action
     *
     * @return \App\DTO\ZmqResponse Response from the door system
     */
    public function sendOpen(string $username, int $timestamp): \App\DTO\ZmqResponse;

    /**
     * Build a command string in the expected format.
     *
     * @param string $environment Environment name ('dev' or 'prod')
     * @param string $action      Action name ('init', 'open', etc.)
     * @param string $username    Username performing the action
     * @param int    $timestamp   Unix timestamp of the action
     */
    public function buildCommand(string $environment, string $action, string $username, int $timestamp): string;
}
