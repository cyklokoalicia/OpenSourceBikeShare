<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\SmsTextNormalizer;

use ashtokalo\translit\Translit;
use BikeShare\SmsTextNormalizer\AsciiSmsTextNormalizer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AsciiSmsTextNormalizerTest extends TestCase
{
    private Translit|MockObject $translitMock;
    private AsciiSmsTextNormalizer $normalizer;
    private string $defaultLocale = 'en';

    protected function setUp(): void
    {
        $this->translitMock = $this->createMock(Translit::class);
        $this->normalizer = new AsciiSmsTextNormalizer(
            $this->translitMock,
            $this->defaultLocale
        );
    }

    public function testNormalizeCallsTranslitWithCorrectParameters(): void
    {
        $inputText = 'Привіт світ!';
        $expectedOutput = 'Hello world!';
        $expectedLocaleParam = $this->defaultLocale . ',cyrillic,ascii';

        $this->translitMock->expects($this->once())
            ->method('convert')
            ->with($inputText, $expectedLocaleParam)
            ->willReturn($expectedOutput);

        $result = $this->normalizer->normalize($inputText);

        $this->assertSame($expectedOutput, $result);
    }

    public function testSetLocaleChangesLocale(): void
    {
        $newLocale = 'uk';
        $this->normalizer->setLocale($newLocale);

        $this->assertSame($newLocale, $this->normalizer->getLocale());
    }

    public function testGetLocaleReturnsCurrentLocale(): void
    {
        $this->assertSame($this->defaultLocale, $this->normalizer->getLocale());
    }

    public function testNormalizeUsesUpdatedLocaleAfterSetLocale(): void
    {
        $inputText = 'Привіт світ!';
        $expectedOutput = 'Pryvit svit!';
        $newLocale = 'uk';

        $this->normalizer->setLocale($newLocale);
        $expectedLocaleParam = $newLocale . ',cyrillic,ascii';

        $this->translitMock->expects($this->once())
            ->method('convert')
            ->with($inputText, $expectedLocaleParam)
            ->willReturn($expectedOutput);

        $result = $this->normalizer->normalize($inputText);

        $this->assertSame($expectedOutput, $result);
    }
}
