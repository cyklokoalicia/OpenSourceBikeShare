<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\Twig;

use BikeShare\Rent\DTO\RentSystemResult;
use BikeShare\Rent\Enum\RentSystemType;
use BikeShare\Twig\RentResultExtension;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AllowMockObjectsWithoutExpectations]
class RentResultExtensionTest extends TestCase
{
    private TranslatorInterface&MockObject $translatorMock;
    private RentResultExtension $extension;

    protected function setUp(): void
    {
        $this->translatorMock = $this->createMock(TranslatorInterface::class);
        $this->extension = new RentResultExtension($this->translatorMock);
    }

    protected function tearDown(): void
    {
        unset($this->translatorMock, $this->extension);
    }

    public function testRenderHtmlWrapsDecoratedParamsInBadge(): void
    {
        $result = new RentSystemResult(
            false,
            'bike.rent.success',
            RentSystemType::WEB,
            ['bikeNumber' => 42, 'currentCode' => '1234', 'newCode' => '5678', 'hasNote' => 'false', 'note' => '']
        );

        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with(
                'bike.rent.success',
                $this->callback(function (array $params): bool {
                    $this->assertSame(
                        '<span class="badge badge-primary">42</span>',
                        $params['bikeNumber']
                    );
                    $this->assertSame(
                        '<span class="badge badge-primary">1234</span>',
                        $params['currentCode']
                    );
                    $this->assertSame(
                        '<span class="badge badge-primary">5678</span>',
                        $params['newCode']
                    );
                    return true;
                })
            )
            ->willReturn('Bike <span class="badge badge-primary">42</span>: Open');

        $rendered = $this->extension->renderHtml($result);

        $this->assertSame('Bike <span class="badge badge-primary">42</span>: Open', $rendered);
    }

    public function testRenderHtmlEscapesUndecoratedScalarParams(): void
    {
        $result = new RentSystemResult(
            false,
            'bike.return.success',
            RentSystemType::WEB,
            [
                'bikeNumber' => 1,
                'note' => '<script>alert(1)</script>',
                'hasNote' => 'true',
            ]
        );

        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with(
                'bike.return.success',
                $this->callback(function (array $params): bool {
                    $this->assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', $params['note']);
                    $this->assertSame('true', $params['hasNote']);
                    $this->assertSame('<span class="badge badge-primary">1</span>', $params['bikeNumber']);
                    return true;
                })
            )
            ->willReturn('rendered');

        $this->extension->renderHtml($result);
    }

    public function testRenderHtmlSkipsDecorationForEmptyValue(): void
    {
        $result = new RentSystemResult(
            false,
            'bike.rent.success',
            RentSystemType::WEB,
            ['bikeNumber' => 7, 'newCode' => '', 'note' => '']
        );

        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with(
                'bike.rent.success',
                $this->callback(function (array $params): bool {
                    $this->assertSame('<span class="badge badge-primary">7</span>', $params['bikeNumber']);
                    $this->assertSame('', $params['newCode']);
                    return true;
                })
            )
            ->willReturn('rendered');

        $this->extension->renderHtml($result);
    }

    public function testRenderHtmlAppliesNl2br(): void
    {
        $result = new RentSystemResult(false, 'bike.rent.success', RentSystemType::WEB, []);

        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->willReturn("line1\nline2");

        $rendered = $this->extension->renderHtml($result);

        $this->assertStringContainsString('<br />', $rendered);
    }

    public function testRenderHtmlMarksFunctionAsHtmlSafe(): void
    {
        $functions = $this->extension->getFunctions();

        $this->assertCount(1, $functions);
        $this->assertSame('rent_result_html', $functions[0]->getName());
        $this->assertContains('html', $functions[0]->getSafe(new \Twig\Node\Node()));
    }
}
