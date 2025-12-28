<?php

declare(strict_types=1);

namespace App\Tests\Support\Kerde;

use App\Service\SSHServiceInterface;

/**
 * Fake SSH Service for testing.
 *
 * Implements SSHServiceInterface to provide configurable responses
 * without real SSH connections.
 */
final class FakeSSHService implements SSHServiceInterface
{
    private bool $shouldSucceed = true;
    private string $errorMessage = '';
    private string $stdout = 'ok';

    public function setSuccess(bool $success): void
    {
        $this->shouldSucceed = $success;
    }

    public function setErrorMessage(string $message): void
    {
        $this->errorMessage = $message;
    }

    public function setStdout(string $stdout): void
    {
        $this->stdout = $stdout;
    }

    public function sendCommand(string $commandName, bool $structured = false): array|string|bool
    {
        if ($structured) {
            return [
                'success' => $this->shouldSucceed,
                'command' => 'fake_command_'.$commandName,
                'stdout' => $this->shouldSucceed ? $this->stdout : '',
                'stderr' => $this->shouldSucceed ? '' : $this->errorMessage,
                'exit_status' => $this->shouldSucceed ? 0 : 1,
                'error' => $this->shouldSucceed ? null : $this->errorMessage,
                'timing' => ['connect_ms' => 10, 'exec_ms' => 20],
            ];
        }

        // Legacy behavior: return false on success, string on error
        if ($this->shouldSucceed) {
            return false;
        }

        return $this->errorMessage ?: 'SSH error';
    }

    public function checkStatus(): bool
    {
        return $this->shouldSucceed;
    }
}
