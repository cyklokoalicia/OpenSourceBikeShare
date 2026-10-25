<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\Twig;

use BikeShare\Twig\QrCodeExtension;
use PHPUnit\Framework\TestCase;

class QrCodeExtensionTest extends TestCase
{
    public function testQrSvgIsScalableInlineSvg(): void
    {
        $svg = (new QrCodeExtension())->qrSvg('https://t.me/example_chat');

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringNotContainsString('<?xml', $svg);
        $this->assertMatchesRegularExpression('/viewBox="0 0 [\d.]+ [\d.]+"/', $svg);
        $this->assertStringContainsString('<rect', $svg);
    }

    public function testModuleSizeScalesTheSvg(): void
    {
        $extension = new QrCodeExtension();

        preg_match('/width="([\d.]+)"/', $extension->qrSvg('https://t.me/example_chat', 2), $small);
        preg_match('/width="([\d.]+)"/', $extension->qrSvg('https://t.me/example_chat', 4), $large);

        $this->assertSame((float)$small[1] * 2, (float)$large[1]);
    }
}
