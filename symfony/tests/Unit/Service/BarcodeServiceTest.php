<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\BarcodeService;
use PHPUnit\Framework\TestCase;

final class BarcodeServiceTest extends TestCase
{
    private BarcodeService $service;

    protected function setUp(): void
    {
        $this->service = new BarcodeService();
    }

    public function testGetCodeReturnsNonEmptyString(): void
    {
        $code = $this->service->getCode();

        $this->assertIsString($code);
        $this->assertNotEmpty($code);
    }

    public function testGetCodeReturnsMinimumLength(): void
    {
        $code = $this->service->getCode();

        // Default sqidsMinLength is 9
        $this->assertGreaterThanOrEqual(9, \strlen($code));
    }

    public function testGetCodeReturnsDifferentValuesOnSubsequentCalls(): void
    {
        $code1 = $this->service->getCode();
        // Sleep 1 second to ensure timestamp format 'ismnydhis' changes
        sleep(1);
        $code2 = $this->service->getCode();

        $this->assertNotEquals($code1, $code2, 'Sequential calls should produce different codes due to timestamp changes');
    }

    public function testGetBarcodeForCodeReturnsArrayWithTwoElements(): void
    {
        $inputCode = 'TEST123';
        $result = $this->service->getBarcodeForCode($inputCode);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testGetBarcodeForCodeReturnsOriginalCodeAsFirstElement(): void
    {
        $inputCode = 'SAMPLE_CODE';
        [$code, $barcode] = $this->service->getBarcodeForCode($inputCode);

        $this->assertSame($inputCode, $code);
    }

    public function testGetBarcodeForCodeReturnsNonEmptyHtmlBarcode(): void
    {
        $inputCode = '1234567890';
        [$code, $barcode] = $this->service->getBarcodeForCode($inputCode);

        $this->assertIsString($barcode);
        $this->assertNotEmpty($barcode);
        $this->assertStringContainsString('<div', $barcode, 'Barcode HTML should contain div tags');
    }

    public function testGetBarcodeForCodeWithEmptyStringThrowsException(): void
    {
        $this->expectException(\Picqer\Barcode\Exceptions\InvalidLengthException::class);
        $this->expectExceptionMessage('You should provide a barcode string');

        $this->service->getBarcodeForCode('');
    }

    public function testGetBarcodeForCodeWithSpecialCharacters(): void
    {
        $specialCode = '_10e_';
        [$code, $barcode] = $this->service->getBarcodeForCode($specialCode);

        $this->assertSame($specialCode, $code);
        $this->assertIsString($barcode);
        $this->assertNotEmpty($barcode);
    }

    public function testCustomSqidsMinLengthInConstructor(): void
    {
        $customLength = 12;
        $customService = new BarcodeService($customLength);

        $code = $customService->getCode();

        $this->assertGreaterThanOrEqual($customLength, \strlen($code));
    }

    public function testGetBarcodeForCodeDeterministic(): void
    {
        $inputCode = 'DETERMINISTIC_TEST';
        [$code1, $barcode1] = $this->service->getBarcodeForCode($inputCode);
        [$code2, $barcode2] = $this->service->getBarcodeForCode($inputCode);

        $this->assertSame($barcode1, $barcode2, 'Same input should produce same barcode HTML');
    }
}
