<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\QrService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\AssetMapperInterface;

final class QrServiceTest extends TestCase
{
    private QrService $service;
    private AssetMapperInterface $assetMapper;
    private string $projectDir;

    protected function setUp(): void
    {
        $this->assetMapper = $this->createMock(AssetMapperInterface::class);
        $this->projectDir = sys_get_temp_dir().'/qr_test_'.uniqid();

        // Create test directory structure
        mkdir($this->projectDir, 0777, true);
        mkdir($this->projectDir.'/public', 0777, true);
        mkdir($this->projectDir.'/assets/images', 0777, true);

        // Create a simple test logo image (1x1 transparent PNG)
        $transparentPng = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );
        file_put_contents($this->projectDir.'/assets/images/golden-logo.png', $transparentPng);

        $this->service = new QrService($this->assetMapper, $this->projectDir);
    }

    protected function tearDown(): void
    {
        // Clean up test directory
        if (is_dir($this->projectDir)) {
            $this->recursiveRemoveDirectory($this->projectDir);
        }
    }

    private function recursiveRemoveDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = array_diff(scandir($directory), ['.', '..']);
        foreach ($items as $item) {
            $path = $directory.'/'.$item;
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }
        rmdir($directory);
    }

    public function testGetQrReturnsNonEmptyString(): void
    {
        $this->assetMapper->expects($this->once())
            ->method('getPublicPath')
            ->with('images/golden-logo.png')
            ->willReturn('/assets/images/golden-logo.png');

        $qrCode = $this->service->getQr('TEST123');

        $this->assertIsString($qrCode);
        $this->assertNotEmpty($qrCode);
    }

    public function testGetQrReturnsPngImage(): void
    {
        $this->assetMapper->expects($this->once())
            ->method('getPublicPath')
            ->with('images/golden-logo.png')
            ->willReturn('/assets/images/golden-logo.png');

        $qrCode = $this->service->getQr('SAMPLE_CODE');

        // Check PNG magic bytes
        $this->assertStringStartsWith("\x89PNG", $qrCode, 'QR code should be a PNG image');
    }

    public function testGetQrGeneratesDifferentCodesForDifferentInputs(): void
    {
        $this->assetMapper->expects($this->exactly(2))
            ->method('getPublicPath')
            ->with('images/golden-logo.png')
            ->willReturn('/assets/images/golden-logo.png');

        $qrCode1 = $this->service->getQr('CODE_A');
        $qrCode2 = $this->service->getQr('CODE_B');

        $this->assertNotEquals($qrCode1, $qrCode2, 'Different inputs should produce different QR codes');
    }

    public function testGetQrGeneratesSameCodeForSameInput(): void
    {
        $this->assetMapper->expects($this->exactly(2))
            ->method('getPublicPath')
            ->with('images/golden-logo.png')
            ->willReturn('/assets/images/golden-logo.png');

        $qrCode1 = $this->service->getQr('DETERMINISTIC');
        $qrCode2 = $this->service->getQr('DETERMINISTIC');

        $this->assertSame($qrCode1, $qrCode2, 'Same input should produce identical QR code');
    }

    public function testGetQrBase64ReturnsBase64EncodedString(): void
    {
        $this->assetMapper->expects($this->once())
            ->method('getPublicPath')
            ->with('images/golden-logo.png')
            ->willReturn('/assets/images/golden-logo.png');

        $base64Code = $this->service->getQrBase64('TEST_BASE64');

        $this->assertIsString($base64Code);
        $this->assertNotEmpty($base64Code);

        // Verify it's valid base64
        $decoded = base64_decode($base64Code, true);
        $this->assertNotFalse($decoded, 'Should be valid base64');

        // Verify decoded data is PNG
        $this->assertStringStartsWith("\x89PNG", $decoded, 'Decoded content should be PNG');
    }

    public function testGetQrBase64ProducesSameResultAsBase64EncodeGetQr(): void
    {
        $this->assetMapper->expects($this->exactly(2))
            ->method('getPublicPath')
            ->with('images/golden-logo.png')
            ->willReturn('/assets/images/golden-logo.png');

        $code = 'CONSISTENCY_TEST';

        $directBase64 = $this->service->getQrBase64($code);
        $manualBase64 = base64_encode($this->service->getQr($code));

        $this->assertSame($manualBase64, $directBase64, 'getQrBase64 should be equivalent to base64_encode(getQr())');
    }

    public function testGetQrHandlesNumericCodes(): void
    {
        $this->assetMapper->expects($this->once())
            ->method('getPublicPath')
            ->with('images/golden-logo.png')
            ->willReturn('/assets/images/golden-logo.png');

        $qrCode = $this->service->getQr('1234567890');

        $this->assertIsString($qrCode);
        $this->assertNotEmpty($qrCode);
        $this->assertStringStartsWith("\x89PNG", $qrCode);
    }

    public function testGetQrHandlesSpecialCharacters(): void
    {
        $this->assetMapper->expects($this->once())
            ->method('getPublicPath')
            ->with('images/golden-logo.png')
            ->willReturn('/assets/images/golden-logo.png');

        $qrCode = $this->service->getQr('TEST-CODE_2025!@#');

        $this->assertIsString($qrCode);
        $this->assertNotEmpty($qrCode);
        $this->assertStringStartsWith("\x89PNG", $qrCode);
    }

    public function testGetQrThrowsExceptionForEmptyString(): void
    {
        $this->assetMapper->expects($this->once())
            ->method('getPublicPath')
            ->with('images/golden-logo.png')
            ->willReturn('/assets/images/golden-logo.png');

        $this->expectException(\BaconQrCode\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Found empty contents');

        $this->service->getQr('');
    }

    public function testGetQrFallbacksToAssetsDirectoryWhenPublicPathMissing(): void
    {
        // Setup: AssetMapper returns a path that doesn't exist
        $this->assetMapper->expects($this->once())
            ->method('getPublicPath')
            ->with('images/golden-logo.png')
            ->willReturn('/nonexistent/path/golden-logo.png');

        // The service should fallback to assets/images/golden-logo.png
        $qrCode = $this->service->getQr('FALLBACK_TEST');

        $this->assertIsString($qrCode);
        $this->assertNotEmpty($qrCode);
        $this->assertStringStartsWith("\x89PNG", $qrCode);
    }

    public function testGetQrHandlesLongCodes(): void
    {
        $this->assetMapper->expects($this->once())
            ->method('getPublicPath')
            ->with('images/golden-logo.png')
            ->willReturn('/assets/images/golden-logo.png');

        $longCode = str_repeat('A', 1000);
        $qrCode = $this->service->getQr($longCode);

        $this->assertIsString($qrCode);
        $this->assertNotEmpty($qrCode);
        $this->assertStringStartsWith("\x89PNG", $qrCode);
    }
}
