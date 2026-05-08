<?php

declare(strict_types=1);

namespace App\Service;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Writer;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class QrService
{
    public function __construct(
        private AssetMapperInterface $assetMapper,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    public function getQr(string $code): string
    {
        $fill = Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(0, 40, 20));
        $renderer = new GDLibRenderer(600, 2, 'png', 9, $fill);
        $qrPng = new Writer($renderer)->writeString($code, 'UTF-8', ErrorCorrectionLevel::H());

        return $this->mergeLogo($qrPng);
    }

    public function getQrBase64(string $code): string
    {
        return base64_encode($this->getQr($code));
    }

    private function mergeLogo(string $qrPng): string
    {
        $publicPath = $this->assetMapper->getPublicPath('images/golden-logo.png');
        $logoPath = $this->projectDir.'/public'.$publicPath;

        if (!file_exists($logoPath)) {
            $logoPath = $this->projectDir.'/assets/images/golden-logo.png';
        }

        $qr = imagecreatefromstring($qrPng);
        $logo = imagecreatefromstring((string) file_get_contents($logoPath));

        $qrW = imagesx($qr);
        $logoW = imagesx($logo);
        $logoH = imagesy($logo);

        $targetW = (int) ($qrW * 0.2);
        $targetH = (int) ($targetW / ($logoW / $logoH));
        $x = (int) (($qrW - $targetW) / 2);
        $y = (int) ((imagesy($qr) - $targetH) / 2);

        imagecopyresampled($qr, $logo, $x, $y, 0, 0, $targetW, $targetH, $logoW, $logoH);
        imagedestroy($logo);

        ob_start();
        imagepng($qr);
        imagedestroy($qr);

        return (string) ob_get_clean();
    }
}
