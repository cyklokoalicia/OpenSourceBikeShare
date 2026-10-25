<?php

declare(strict_types=1);

namespace BikeShare\Twig;

use Com\Tecnick\Barcode\Barcode;
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
        // Negative width/height means "pixels per module"; the SVG carries its own viewBox,
        // so CSS-resized QR codes scale centered.
        return (new Barcode())
            ->getBarcodeObj('QRCODE,M', $text, -$modulePx, -$modulePx, 'black')
            ->getInlineSvgCode();
    }
}
