<?php

namespace App\Helper;

use Hashids\Hashids;
use Picqer\Barcode\BarcodeGeneratorHTML;

class Barcode
{
    public function getBarcode($member): array
    {
        $generator = new BarcodeGeneratorHTML();
        $code = $member->getId() . '' . $member->getId() . '' . $member->getUser()->getId();
        $hashids = new Hashids($code, 8);
        $code = $hashids->encode($code);
        $barcode = $generator->getBarcode($code, $generator::TYPE_CODE_128, 2, 90);
        return [$code, $barcode];
    }
}
