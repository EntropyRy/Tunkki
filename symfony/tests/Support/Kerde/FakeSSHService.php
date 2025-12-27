<?php

declare(strict_types=1);

namespace App\Tests\Support\Kerde;

use App\Service\SSHService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * Fake SSH Service for testing.
 *
 * Extends SSHService to satisfy type hints but overrides methods
 * to return configurable responses without real SSH connections.
 */
final class FakeSSHService extends SSHService
{
    private bool $shouldSucceed = true;
    private string $errorMessage = '';

    public function __construct()
    {
        // Pass a minimal parameter bag to parent
        parent::__construct(new ParameterBag([
            'recording.host' => 'localhost',
            'recording.port' => 22,
            'recording.user' => 'test',
            'recording.pass' => 'test',
        ]));
    }

    public function setSuccess(bool $success): void
    {
        $this->shouldSucceed = $success;
    }

    public function setErrorMessage(string $message): void
    {
        $this->errorMessage = $message;
    }

    #[\Override]
    public function sendCommand(string $commandName, bool $structured = false): array|string|bool
    {
        if ($structured) {
            return [
                'success' => $this->shouldSucceed,
                'command' => 'fake_command',
                'stdout' => $this->shouldSucceed ? 'ok' : '',
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

    #[\Override]
    public function checkStatus(): bool
    {
        return $this->shouldSucceed;
    }
}
