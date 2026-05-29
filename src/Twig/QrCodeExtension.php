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
        $svg = $barcode->getBarcodeSVGcode($modulePx, $modulePx, 'black');

        // TCPDF emits width/height but omits viewBox, so CSS-resized SVGs keep their
        // pattern in the top-left corner. Inject viewBox so the QR scales centered.
        if (preg_match('/<svg\s+width="(\d+)"\s+height="(\d+)"/', $svg, $m) === 1) {
            $svg = preg_replace(
                '/<svg\s+/',
                '<svg viewBox="0 0 ' . $m[1] . ' ' . $m[2] . '" preserveAspectRatio="xMidYMid meet" ',
                $svg,
                1,
            );
        }

        return $svg;
    }
}
