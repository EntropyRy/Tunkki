<?php

declare(strict_types=1);

namespace App\Service;

use Picqer\Barcode\BarcodeGeneratorHTML;
use Sqids\Sqids;

final readonly class BarcodeService
{
    private Sqids $sqids;

    public function __construct(
        private int $sqidsMinLength = 9,
    ) {
        $this->sqids = new Sqids('', $this->sqidsMinLength);
    }

    /**
     * Generate a unique code based on current timestamp.
     *
     * Uses ISO week (1-53), seconds (0-59), minutes (0-59), month (1-12),
     * year (2 digits), day (1-31), hour (0-23), seconds (0-59) to create
     * a unique integer, then encodes it with Sqids.
     */
    public function getCode(): string
    {
        $uniquecode = (int) date('ismnydhis');

        return $this->sqids->encode([$uniquecode]);
    }

    /**
     * Generate HTML barcode for a given code.
     *
     * @return array{0: string, 1: string} [code, html_barcode]
     */
    public function getBarcodeForCode(string $code): array
    {
        $generator = new BarcodeGeneratorHTML();
        $barcode = $generator->getBarcode($code, BarcodeGeneratorHTML::TYPE_CODE_128, 2, 90);

        return [$code, $barcode];
    }
}
