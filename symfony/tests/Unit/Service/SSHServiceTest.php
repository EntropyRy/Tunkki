<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\SSHService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

final class SSHServiceTest extends TestCase
{
    private ParameterBag $parameterBag;
    private SSHService $service;

    protected function setUp(): void
    {
        // Mock parameter bag with test SSH configuration
        $this->parameterBag = new ParameterBag([
            'recording.host' => 'localhost',
            'recording.port' => 22,
            'recording.user' => 'testuser',
            'recording.pass' => 'testpass',
            'recording.script.start' => 'start_stream.sh',
            'recording.script.stop' => 'stop_stream.sh',
            'recording.script.check' => 'check_stream.sh',
        ]);

        $this->service = new SSHService($this->parameterBag);
    }

    public function testSendCommandReturnsFalseOnMissingParameter(): void
    {
        // Test legacy mode with missing parameter
        $result = $this->service->sendCommand('nonexistent');

        // Legacy mode: returns string error message (not false)
        $this->assertIsString($result);
        $this->assertStringContainsString('Missing parameter', $result);
    }

    public function testSendCommandStructuredReturnsMissingParameterError(): void
    {
        // Test structured mode with missing parameter
        $result = $this->service->sendCommand('nonexistent', structured: true);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Missing parameter', $result['error']);
        $this->assertSame('recording.script.nonexistent', $result['command']);
    }

    public function testCheckStatusReturnsFalseOnMissingParameter(): void
    {
        // Remove the check script parameter
        $parameterBag = new ParameterBag([
            'recording.host' => 'localhost',
            'recording.port' => 22,
            'recording.user' => 'testuser',
            'recording.pass' => 'testpass',
        ]);

        $service = new SSHService($parameterBag);
        $result = $service->checkStatus();

        $this->assertFalse($result);
    }

    public function testStructuredResultFormat(): void
    {
        $result = $this->service->sendCommand('start', structured: true);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('command', $result);
        $this->assertArrayHasKey('stdout', $result);
        $this->assertArrayHasKey('stderr', $result);
        $this->assertArrayHasKey('exit_status', $result);
        $this->assertArrayHasKey('error', $result);
        $this->assertArrayHasKey('timing', $result);
        $this->assertIsArray($result['timing']);
        $this->assertArrayHasKey('connect_ms', $result['timing']);
        $this->assertArrayHasKey('exec_ms', $result['timing']);
    }

    public function testLegacyModeReturnsStringOnError(): void
    {
        // Legacy mode (structured=false) should return string on error
        $result = $this->service->sendCommand('start', structured: false);

        // Will fail due to no SSH2 connection, should return string error
        $this->assertIsString($result);
    }

    public function testServiceConstructsWithParameterBag(): void
    {
        $service = new SSHService($this->parameterBag);

        $this->assertInstanceOf(SSHService::class, $service);
    }

    public function testParameterBagIsUsedForCommandLookup(): void
    {
        // Verify that the service looks up commands in the parameter bag
        $result = $this->service->sendCommand('start', structured: true);

        $this->assertIsArray($result);
        // Command should be the script from parameter bag
        $this->assertSame('start_stream.sh', $result['command']);
    }

    public function testMultipleCommandNamesHaveCorrectParameterKeys(): void
    {
        $startResult = $this->service->sendCommand('start', structured: true);
        $stopResult = $this->service->sendCommand('stop', structured: true);
        $checkMissing = $this->service->sendCommand('missing', structured: true);

        $this->assertSame('start_stream.sh', $startResult['command']);
        $this->assertSame('stop_stream.sh', $stopResult['command']);
        $this->assertSame('recording.script.missing', $checkMissing['command']);
    }
}
