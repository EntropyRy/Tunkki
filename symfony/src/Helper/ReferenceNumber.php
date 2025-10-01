<?php

namespace App\Helper;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ReferenceNumber
{
    public function __construct(protected ParameterBagInterface $bag)
    {
    }

    public function calculateReferenceNumber(object $object, int $add, int $start): int
    {
        $ki = 0;
        $summa = 0;
        $kertoimet = [7, 3, 1];
        $id = (int) $object->getId() + $add;
        $viite = $start.$id;

        for ($i = strlen($viite); $i > 0; --$i) {
            $summa += (int) substr($viite, $i - 1, 1) * $kertoimet[$ki++ % 3];
        }
        $cast = $viite.((10 - ($summa % 10)) % 10);

        return (int) $cast;
    }
}
