<?php

namespace App\Helper;

use SimpleSoftwareIO\QrCode\Generator;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class Qr
{
    public function __construct(
        private readonly AssetMapperInterface $assetMapper,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir
    ) {
    }

    public function getQr(string $code): string
    {
        $generator = new Generator();

        // Get the public path from AssetMapper
        $publicPath = $this->assetMapper->getPublicPath('images/golden-logo.png');

        // Convert the public path to filesystem path
        $logoPath = $this->projectDir . '/public' . $publicPath;

        // Fallback to original location if the mapped file doesn't exist
        if (!file_exists($logoPath)) {
            $logoPath = $this->projectDir . '/assets/images/golden-logo.png';
        }

        return $generator
                ->format('png')
                ->style('round')
                ->eye('circle')
                ->margin(2)
                ->size(600)
                ->gradient(0, 40, 40, 40, 40, 0, 'radial')
                ->errorCorrection('H')
                ->merge($logoPath, .2)
                ->generate($code);
    }

    public function getQrBase64(string $code): string
    {
        return base64_encode($this->getQr($code));
    }
}
