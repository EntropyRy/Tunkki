<?php

namespace App\Controller;

use Picqer\Barcode\BarcodeGeneratorHTML;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @IsGranted("ROLE_USER")
 */
class BarcodeController extends AbstractController
{
    #[Route('/kerde/barcodes', name: 'entropy_kerde_barcodes')]
    public function index(): Response
    {
        $generator = new BarcodeGeneratorHTML();
        $barcodes['10€'] = $generator->getBarcode('_10e_', $generator::TYPE_CODE_128, 2, 90);
        $barcodes['20€'] = $generator->getBarcode('_20e_', $generator::TYPE_CODE_128, 2, 90);
        $barcodes['Cancel'] = $generator->getBarcode('_CANCEL_', $generator::TYPE_CODE_128, 2, 90);
        $barcodes['Manual'] = $generator->getBarcode('1812271001', $generator::TYPE_CODE_128, 2, 90);
        $barcodes['Statistics'] = $generator->getBarcode('0348030005', $generator::TYPE_CODE_128, 2, 90);
        return $this->render('kerde/barcodes.html.twig', [
            'barcodes' => $barcodes
        ]);
    }
}
