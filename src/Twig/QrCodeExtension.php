<?php

declare(strict_types=1);

namespace BikeShare\Twig;

use TCPDF2DBarcode;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class QrCodeExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('qr_svg', $this->qrSvg(...), ['is_safe' => ['html']]),
        ];
    }

    public function qrSvg(string $text, int $modulePx = 4): string
    {
        $barcode = new TCPDF2DBarcode($text, 'QRCODE,M');

        return $barcode->getBarcodeSVGcode($modulePx, $modulePx, 'black');
    }
}
