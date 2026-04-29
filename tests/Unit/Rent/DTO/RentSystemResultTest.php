<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\Rent\DTO;

use BikeShare\Rent\DTO\RentSystemResult;
use BikeShare\Rent\Enum\RentSystemType;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AllowMockObjectsWithoutExpectations]
class RentSystemResultTest extends TestCase
{
    private TranslatorInterface&MockObject $translator;

    protected function setUp(): void
    {
        $this->translator = $this->createMock(TranslatorInterface::class);
    }

    public function testTransAutoInjectsChannelFromSystemType(): void
    {
        $result = new RentSystemResult(false, 'bike.rent.success', RentSystemType::WEB, ['bikeNumber' => 1]);

        $this->translator
            ->expects($this->once())
            ->method('trans')
            ->willReturnCallback(function (string $code, array $params, ?string $domain): string {
                $this->assertSame('bike.rent.success', $code);
                $this->assertSame('rentSystem', $domain);
                $this->assertSame('web', $params['channel']);
                return 'rendered';
            });

        $result->trans($this->translator);
    }

    public function testTransPreservesCallerProvidedChannel(): void
    {
        $result = new RentSystemResult(
            false,
            'bike.rent.success',
            RentSystemType::WEB,
            ['bikeNumber' => 1, 'channel' => 'custom']
        );

        $this->translator
            ->expects($this->once())
            ->method('trans')
            ->willReturnCallback(function (string $code, array $params): string {
                $this->assertSame('custom', $params['channel']);
                return 'rendered';
            });

        $result->trans($this->translator);
    }

    public function testTransEscapesScalarParamsOnSuccessPath(): void
    {
        $result = new RentSystemResult(
            false,
            'bike.return.success',
            RentSystemType::WEB,
            ['standName' => '<script>alert(1)</script>', 'bikeNumber' => 7]
        );

        $this->translator
            ->expects($this->once())
            ->method('trans')
            ->willReturnCallback(function (string $code, array $params): string {
                $this->assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', $params['standName']);
                $this->assertSame('7', $params['bikeNumber']);
                return 'rendered';
            });

        $result->trans($this->translator);
    }

    public function testTransEscapesScalarParamsOnErrorPath(): void
    {
        $result = new RentSystemResult(
            true,
            'bike.return.error.stand_not_found',
            RentSystemType::WEB,
            ['standName' => '<img src=x onerror=alert(1)>']
        );

        $this->translator
            ->expects($this->once())
            ->method('trans')
            ->willReturnCallback(function (string $code, array $params): string {
                $this->assertSame(
                    '&lt;img src=x onerror=alert(1)&gt;',
                    $params['standName']
                );
                return 'rendered';
            });

        $result->trans($this->translator);
    }

    public function testTransEscapesScalarParamsOnSmsPath(): void
    {
        $result = new RentSystemResult(
            false,
            'bike.rent.success',
            RentSystemType::SMS,
            ['note' => '<b>x</b>']
        );

        $this->translator
            ->expects($this->once())
            ->method('trans')
            ->willReturnCallback(function (string $code, array $params): string {
                $this->assertSame('&lt;b&gt;x&lt;/b&gt;', $params['note']);
                return 'rendered';
            });

        $result->trans($this->translator);
    }

    public function testTransResolvesTranslatableInterfaceParams(): void
    {
        $nested = $this->createMock(TranslatableInterface::class);
        $nested
            ->expects($this->once())
            ->method('trans')
            ->with($this->translator, 'en')
            ->willReturn('<resolved>');

        $result = new RentSystemResult(
            false,
            'bike.rent.success',
            RentSystemType::WEB,
            ['inner' => $nested]
        );

        $this->translator
            ->expects($this->once())
            ->method('trans')
            ->willReturnCallback(function (string $code, array $params): string {
                // Resolved value is then htmlspecialchars-escaped.
                $this->assertSame('&lt;resolved&gt;', $params['inner']);
                return 'rendered';
            });

        $result->trans($this->translator, 'en');
    }

    public function testTransAppliesNl2brOnNonSmsChannel(): void
    {
        $result = new RentSystemResult(false, 'bike.rent.success', RentSystemType::WEB);

        $this->translator->method('trans')->willReturn("line1\nline2");

        $this->assertStringContainsString('<br />', $result->trans($this->translator));
    }

    public function testTransSkipsNl2brOnSms(): void
    {
        $result = new RentSystemResult(false, 'bike.rent.success', RentSystemType::SMS);

        $this->translator->method('trans')->willReturn("line1\nline2");

        $this->assertSame("line1\nline2", $result->trans($this->translator));
    }
}
