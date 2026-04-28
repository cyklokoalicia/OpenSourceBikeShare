<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\SmsCommand;

use PHPUnit\Framework\Attributes\DataProvider;
use BikeShare\App\Entity\User;
use BikeShare\Repository\BikeRepository;
use BikeShare\SmsCommand\Exception\ValidationException;
use BikeShare\SmsCommand\LastCommand;
use Generator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatableMessage;

class LastCommandTest extends TestCase
{
    private BikeRepository&MockObject $bikeRepositoryMock;
    private LastCommand $command;

    protected function setUp(): void
    {
        $this->bikeRepositoryMock = $this->createMock(BikeRepository::class);
        $this->command = new LastCommand($this->bikeRepositoryMock);
    }

    protected function tearDown(): void
    {
        unset($this->bikeRepositoryMock, $this->command);
    }

    #[DataProvider('invokeDataProvider')]
    public function testInvoke(array $history, string $expectedHistory): void
    {
        $bikeNumber = 123;
        $this->bikeRepositoryMock
            ->expects($this->once())
            ->method('findItem')
            ->with($bikeNumber)
            ->willReturn(['id' => $bikeNumber]);
        $this->bikeRepositoryMock
            ->expects($this->once())
            ->method('findItemLastUsage')
            ->with($bikeNumber)
            ->willReturn(['history' => $history]);

        $result = ($this->command)($this->createStub(User::class), $bikeNumber);

        $this->assertInstanceOf(TranslatableMessage::class, $result);
        $this->assertSame('command.last.message', $result->getMessage());
        $this->assertSame(
            ['bikeNumber' => $bikeNumber, 'history' => $expectedHistory],
            $result->getParameters()
        );
    }

    public function testInvokeThrowsWhenBikeInfoEmpty(): void
    {
        $this->bikeRepositoryMock->expects($this->once())->method('findItem')->with(123)->willReturn([]);
        $this->bikeRepositoryMock->expects($this->never())->method('findItemLastUsage');
        $this->expectException(ValidationException::class);

        ($this->command)($this->createStub(User::class), 123);
    }

    public function testGetHelpMessage(): void
    {
        $this->bikeRepositoryMock->expects($this->never())->method('findItem');

        $help = $this->command->getHelpMessage();
        $this->assertInstanceOf(TranslatableMessage::class, $help);
        $this->assertSame('command.last.help', $help->getMessage());
    }

    public static function invokeDataProvider(): Generator
    {
        yield 'empty history' => [
            'history' => [],
            'expectedHistory' => '',
        ];
        yield 'history with RETURN action and stand name' => [
            'history' => [
                ['action' => 'RETURN', 'standName' => 'Central Station', 'userName' => null, 'parameter' => null],
            ],
            'expectedHistory' => 'Central Station',
        ];
        yield 'history with REVERT action and stand name' => [
            'history' => [
                ['action' => 'REVERT', 'standName' => 'Park', 'userName' => null, 'parameter' => null],
            ],
            'expectedHistory' => '*,Park',
        ];
        yield 'history with null stand name' => [
            'history' => [
                ['action' => 'RENT', 'standName' => null, 'userName' => 'John', 'parameter' => 'Quick'],
            ],
            'expectedHistory' => 'John(Quick)',
        ];
    }
}
