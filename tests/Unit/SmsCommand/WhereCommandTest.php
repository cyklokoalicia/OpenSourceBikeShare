<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\NoteRepository;
use BikeShare\SmsCommand\Exception\ValidationException;
use BikeShare\SmsCommand\WhereCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatableMessage;

class WhereCommandTest extends TestCase
{
    private BikeRepository&MockObject $bikeRepositoryMock;
    private NoteRepository&MockObject $noteRepositoryMock;
    private WhereCommand $command;

    protected function setUp(): void
    {
        $this->bikeRepositoryMock = $this->createMock(BikeRepository::class);
        $this->noteRepositoryMock = $this->createMock(NoteRepository::class);
        $this->command = new WhereCommand($this->bikeRepositoryMock, $this->noteRepositoryMock);
    }

    protected function tearDown(): void
    {
        unset($this->bikeRepositoryMock, $this->noteRepositoryMock, $this->command);
    }

    public function testBikeAtStand(): void
    {
        $bikeNumber = 42;
        $this->bikeRepositoryMock
            ->expects($this->once())
            ->method('findItem')
            ->with($bikeNumber)
            ->willReturn(['bikeNumber' => $bikeNumber]);
        $this->noteRepositoryMock
            ->expects($this->once())
            ->method('findBikeNote')
            ->with($bikeNumber)
            ->willReturn([['note' => 'Flat tire']]);
        $this->bikeRepositoryMock
            ->expects($this->once())
            ->method('findBikeCurrentUsage')
            ->with($bikeNumber)
            ->willReturn([
                'number' => '123456789',
                'userName' => null,
                'standName' => 'STAND1',
            ]);

        $result = ($this->command)($this->createStub(User::class), $bikeNumber);

        $this->assertInstanceOf(TranslatableMessage::class, $result);
        $this->assertSame('command.where.at_stand', $result->getMessage());
        $this->assertSame(
            ['bikeNumber' => $bikeNumber, 'standName' => 'STAND1', 'note' => 'Flat tire'],
            $result->getParameters()
        );
    }

    public function testBikeRented(): void
    {
        $bikeNumber = 42;
        $this->bikeRepositoryMock
            ->expects($this->once())
            ->method('findItem')
            ->with($bikeNumber)
            ->willReturn(['bikeNumber' => $bikeNumber]);
        $this->noteRepositoryMock
            ->expects($this->once())
            ->method('findBikeNote')
            ->with($bikeNumber)
            ->willReturn([['note' => 'Broken']]);
        $this->bikeRepositoryMock
            ->expects($this->once())
            ->method('findBikeCurrentUsage')
            ->with($bikeNumber)
            ->willReturn([
                'number' => '987654321',
                'userName' => 'John',
                'standName' => null,
            ]);

        $result = ($this->command)($this->createStub(User::class), $bikeNumber);

        $this->assertInstanceOf(TranslatableMessage::class, $result);
        $this->assertSame('command.where.in_use', $result->getMessage());
        $this->assertSame(
            [
                'bikeNumber' => $bikeNumber,
                'userName' => 'John',
                'phone' => '987654321',
                'note' => 'Broken',
            ],
            $result->getParameters()
        );
    }

    public function testBikeNotFoundThrows(): void
    {
        $this->bikeRepositoryMock->expects($this->once())->method('findItem')->with(42)->willReturn([]);
        $this->noteRepositoryMock->expects($this->never())->method('findBikeNote');
        $this->expectException(ValidationException::class);

        ($this->command)($this->createStub(User::class), 42);
    }

    public function testGetHelpMessage(): void
    {
        $this->bikeRepositoryMock->expects($this->never())->method('findItem');
        $this->noteRepositoryMock->expects($this->never())->method('findBikeNote');

        $help = $this->command->getHelpMessage();
        $this->assertInstanceOf(TranslatableMessage::class, $help);
        $this->assertSame('command.where.help', $help->getMessage());
    }
}
