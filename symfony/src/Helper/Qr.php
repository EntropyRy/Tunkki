<?php

namespace App\Helper;

use SimpleSoftwareIO\QrCode\Generator;

class Qr
{
    public function getQr(string $code): string
    {
        $generator = new Generator();
        return $generator
                ->format('png')
                ->style('round')
                ->eye('circle')
                ->margin(2)
                ->size(600)
                ->gradient(0, 40, 40, 40, 40, 0, 'radial')
                ->errorCorrection('H')
                ->merge('images/golden-logo.png', .2)
                ->generate($code);
    }
    public function getQrBase64(string $code): string
    {
        return base64_encode($this->getQr($code));
    }
}
