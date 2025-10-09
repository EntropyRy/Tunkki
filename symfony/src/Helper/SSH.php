<?php

declare(strict_types=1);

namespace App\Helper;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Hardened SSH helper that:
 * - Validates connection and authentication.
 * - Executes predefined script commands (from parameter bag keys recording.script.<name>).
 * - Can return either legacy (string|false) or structured (array) results.
 *
 * Backwards compatibility:
 *   sendCommand($name) without the second argument still returns:
 *     - false on (apparent) success (old semantics)
 *     - string (stderr or internal error message) on failure
 *
 * New usage:
 *   $result = $ssh->sendCommand('start', true);
 *   // $result = [
 *   //   'success' => bool,
 *   //   'command' => string,
 *   //   'stdout' => string,
 *   //   'stderr' => string,
 *   //   'exit_status' => int|null,
 *   //   'error' => string|null,
 *   //   'timing' => ['connect_ms' => int, 'exec_ms' => int],
 *   // ]
 */
class SSH
{
    private ?array $lastResult = null;

    public function __construct(private readonly ParameterBagInterface $bag)
    {
    }

    /**
     * Execute a predefined command identified by suffix <text> in parameter key: recording.script.<text>.
     *
     * Legacy mode (structured = false):
     *   Returns false when command considered successful (no stderr and exit_status == 0 or null),
     *   or returns a string describing the error (stderr output or internal error).
     *
     * Structured mode (structured = true):
     *   Returns an array (see class docblock).
     *
     * @param string $text       Suffix after 'recording.script.' in parameters
     * @param bool   $structured Whether to return structured result
     */
    public function sendCommand(
        string $text,
        bool $structured = false,
    ): array|string|bool {
        $paramKey = 'recording.script.'.$text;

        if (!$this->bag->has($paramKey)) {
            return $this->finalizeResult(
                success: false,
                command: $paramKey,
                stdout: '',
                stderr: '',
                exitStatus: null,
                error: "Missing parameter '$paramKey'",
                structured: $structured,
            );
        }

        $command = (string) $this->bag->get($paramKey);

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
                error: "Failed to execute SSH command '$command'",
                structured: $structured,
                connectMs: $connectMs,
            );
        }

        $errorStream = @ssh2_fetch_stream($stream, \SSH2_STREAM_STDERR);
        if (false === $errorStream) {
            // Still proceed, but note missing stderr stream
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

        // Retrieve exit status (may be null if not provided and function may not exist)
        $exitStatus = null;
        if (\function_exists('ssh2_get_exit_status')) {
            $statusRaw = @ssh2_get_exit_status($stream);
            if (null !== $statusRaw && false !== $statusRaw) {
                $exitStatus = (int) $statusRaw;
            }
        }

        $execMs = (int) round((microtime(true) - $execStart) * 1000);

        $success =
            '' === $stderr && (null === $exitStatus || 0 === $exitStatus);

        return $this->finalizeResult(
            success: $success,
            command: $command,
            stdout: $stdout,
            stderr: $stderr,
            exitStatus: $exitStatus,
            error: $success
                ? null
                : ('' !== $stderr
                    ? trim($stderr)
                    : 'Command failed'),
            structured: $structured,
            connectMs: $connectMs,
            execMs: $execMs,
        );
    }

    /**
     * Check status via 'recording.script.check' command expecting "1" for true, anything else false.
     */
    public function checkStatus(): bool
    {
        $paramKey = 'recording.script.check';
        if (!$this->bag->has($paramKey)) {
            return false;
        }
        $command = (string) $this->bag->get($paramKey);

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
     * Lightweight connectivity test (does not run a command).
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
     * Retrieve the last structured result (if any) regardless of legacy/structured call style.
     */
    public function getLastResult(): ?array
    {
        return $this->lastResult;
    }

    /**
     * Internal: establish SSH connection and authenticate or throw.
     *
     * @return resource
     *
     * @throws \RuntimeException
     */
    protected function getConnectionOrFail()
    {
        $host = (string) $this->bag->get('recording.host');
        $port = (int) $this->bag->get('recording.port');
        $user = (string) $this->bag->get('recording.user');
        $pass = (string) $this->bag->get('recording.pass');

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

        // Legacy behavior: return false on success, string on error.
        if ($success) {
            return false;
        }

        // Prefer stderr message; fallback to generic error.
        return '' !== $stderr ? trim($stderr) : $error ?? 'Unknown SSH error';
    }
}
