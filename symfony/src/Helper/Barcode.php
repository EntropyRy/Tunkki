<?php

declare(strict_types=1);

namespace App\Helper;

use Picqer\Barcode\BarcodeGeneratorHTML;
use Sqids\Sqids;

class Barcode
{
    public function getCode(): string
    {
        $uniquecode = (int) date('ismnydhis');
        $sqid = new Sqids('', 9);

        return $sqid->encode([$uniquecode]);
    }

    public function getBarcodeForCode(string $code): array
    {
        $generator = new BarcodeGeneratorHTML();
        $barcode = $generator->getBarcode($code, BarcodeGeneratorHTML::TYPE_CODE_128, 2, 90);

        return [$code, $barcode];
    }
}
