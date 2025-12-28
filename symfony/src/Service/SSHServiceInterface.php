<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Interface for SSH remote command execution service.
 *
 * Provides methods for:
 * - Executing predefined script commands on a remote host
 * - Checking remote status
 */
interface SSHServiceInterface
{
    /**
     * Execute a predefined command identified by suffix in parameter key: recording.script.<text>.
     *
     * @param string $commandName Suffix after 'recording.script.' in parameters
     * @param bool   $structured  Whether to return structured result array
     *
     * @return array|string|false Array if structured=true, false on success (legacy), string error on failure (legacy)
     */
    public function sendCommand(string $commandName, bool $structured = false): array|string|bool;

    /**
     * Check remote status via 'recording.script.check' command.
     *
     * Expects "1" for true, anything else is false.
     */
    public function checkStatus(): bool;
}
