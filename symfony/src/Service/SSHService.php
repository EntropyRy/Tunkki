<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * SSH Service for executing remote commands via SSH2.
 *
 * Provides:
 * - Validated connection and authentication
 * - Execution of predefined script commands (from parameter bag: recording.script.<name>)
 * - Both legacy (string|false) and structured (array) result formats
 * - Connection testing and status checking
 *
 * Backwards compatibility:
 *   sendCommand($name) without the second argument returns:
 *     - false on success (legacy semantics)
 *     - string (stderr or internal error message) on failure
 *
 * New structured usage:
 *   $result = $ssh->sendCommand('start', true);
 *   // Returns: [
 *   //   'success' => bool,
 *   //   'command' => string,
 *   //   'stdout' => string,
 *   //   'stderr' => string,
 *   //   'exit_status' => int|null,
 *   //   'error' => string|null,
 *   //   'timing' => ['connect_ms' => int, 'exec_ms' => int],
 *   // ]
 */
final class SSHService
{
    private ?array $lastResult = null;

    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
    ) {
    }

    /**
     * Execute a predefined command identified by suffix in parameter key: recording.script.<text>.
     *
     * @param string $commandName Suffix after 'recording.script.' in parameters
     * @param bool   $structured  Whether to return structured result array
     *
     * @return array|string|false Array if structured=true, false on success (legacy), string error on failure (legacy)
     */
    public function sendCommand(
        string $commandName,
        bool $structured = false,
    ): array|string|bool {
        $paramKey = 'recording.script.'.$commandName;

        if (!$this->parameterBag->has($paramKey)) {
            return $this->finalizeResult(
                success: false,
                command: $paramKey,
                stdout: '',
                stderr: '',
                exitStatus: null,
                error: "Missing parameter '{$paramKey}'",
                structured: $structured,
            );
        }

        $command = (string) $this->parameterBag->get($paramKey);

        $connectStart = microtime(true);
        try {
            $connection = $this->getConnectionOrFail();
        } catch (\Throwable $e) {
            return $this->finalizeResult(
                success: false,
                command: $command,
                stdout: '',
                stderr: '',
                exitStatus: null,
                error: $e->getMessage(),
                structured: $structured,
            );
        }
        $connectMs = (int) round((microtime(true) - $connectStart) * 1000);

        $execStart = microtime(true);
        $stream = @ssh2_exec($connection, $command);
        if (false === $stream) {
            return $this->finalizeResult(
                success: false,
                command: $command,
                stdout: '',
                stderr: '',
                exitStatus: null,
                error: "Failed to execute SSH command '{$command}'",
                structured: $structured,
                connectMs: $connectMs,
            );
        }

        $errorStream = @ssh2_fetch_stream($stream, \SSH2_STREAM_STDERR);
        if (false === $errorStream) {
            $errorStream = null;
        }

        // Ensure blocking to reliably read full output
        stream_set_blocking($stream, true);
        if ($errorStream) {
            stream_set_blocking($errorStream, true);
        }

        $stdout = stream_get_contents($stream) ?: '';
        $stderr = $errorStream ? (stream_get_contents($errorStream) ?: '') : '';

        if ($errorStream) {
            fclose($errorStream);
        }
        fclose($stream);

        // Retrieve exit status (may be null if not provided)
        $exitStatus = null;
        if (\function_exists('ssh2_get_exit_status')) {
            $statusRaw = @ssh2_get_exit_status($stream);
            if (null !== $statusRaw && false !== $statusRaw) {
                $exitStatus = (int) $statusRaw;
            }
        }

        $execMs = (int) round((microtime(true) - $execStart) * 1000);

        $success = '' === $stderr && (null === $exitStatus || 0 === $exitStatus);

        return $this->finalizeResult(
            success: $success,
            command: $command,
            stdout: $stdout,
            stderr: $stderr,
            exitStatus: $exitStatus,
            error: $success
                ? null
                : ('' !== $stderr ? trim($stderr) : 'Command failed'),
            structured: $structured,
            connectMs: $connectMs,
            execMs: $execMs,
        );
    }

    /**
     * Check remote status via 'recording.script.check' command.
     *
     * Expects "1" for true, anything else is false.
     */
    public function checkStatus(): bool
    {
        $paramKey = 'recording.script.check';
        if (!$this->parameterBag->has($paramKey)) {
            return false;
        }
        $command = (string) $this->parameterBag->get($paramKey);

        try {
            $connection = $this->getConnectionOrFail();
        } catch (\Throwable) {
            return false;
        }

        $stream = @ssh2_exec($connection, $command);
        if (false === $stream) {
            return false;
        }

        stream_set_blocking($stream, true);
        $output = stream_get_contents($stream);
        fclose($stream);

        return '1' === trim((string) $output);
    }

    /**
     * Test SSH connectivity without executing a command.
     */
    public function testConnection(): bool
    {
        try {
            $this->getConnectionOrFail();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Retrieve the last structured result (if any) regardless of call style.
     */
    public function getLastResult(): ?array
    {
        return $this->lastResult;
    }

    /**
     * Establish SSH connection and authenticate or throw.
     *
     * @return resource SSH connection resource
     *
     * @throws \RuntimeException on connection or authentication failure
     */
    private function getConnectionOrFail()
    {
        $host = (string) $this->parameterBag->get('recording.host');
        $port = (int) $this->parameterBag->get('recording.port');
        $user = (string) $this->parameterBag->get('recording.user');
        $pass = (string) $this->parameterBag->get('recording.pass');

        $connection = @ssh2_connect($host, $port);
        if (false === $connection) {
            throw new \RuntimeException("SSH connect failed to {$host}:{$port}");
        }

        $authed = @ssh2_auth_password($connection, $user, $pass);
        if (false === $authed) {
            throw new \RuntimeException("SSH authentication failed for user '{$user}' at {$host}:{$port}");
        }

        return $connection;
    }

    /**
     * Consolidate and store result; return according to requested mode.
     *
     * @return array|string|false
     */
    private function finalizeResult(
        bool $success,
        string $command,
        string $stdout,
        string $stderr,
        ?int $exitStatus,
        ?string $error,
        bool $structured,
        ?int $connectMs = null,
        ?int $execMs = null,
    ): array|string|bool {
        $result = [
            'success' => $success,
            'command' => $command,
            'stdout' => $stdout,
            'stderr' => $stderr,
            'exit_status' => $exitStatus,
            'error' => $error,
            'timing' => [
                'connect_ms' => $connectMs,
                'exec_ms' => $execMs,
            ],
        ];

        $this->lastResult = $result;

        if ($structured) {
            return $result;
        }

        // Legacy behavior: return false on success, string on error
        if ($success) {
            return false;
        }

        // Prefer stderr message; fallback to generic error
        return '' !== $stderr ? trim($stderr) : $error ?? 'Unknown SSH error';
    }
}
