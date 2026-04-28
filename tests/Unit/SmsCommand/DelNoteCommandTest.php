<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\NoteRepository;
use BikeShare\Repository\StandRepository;
use BikeShare\SmsCommand\DelNoteCommand;
use BikeShare\SmsCommand\Exception\ValidationException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatableMessage;

class DelNoteCommandTest extends TestCase
{
    private BikeRepository&MockObject $bikeRepositoryMock;
    private StandRepository&MockObject $standRepositoryMock;
    private NoteRepository&MockObject $noteRepositoryMock;
    private DelNoteCommand $command;

    protected function setUp(): void
    {
        $this->bikeRepositoryMock = $this->createMock(BikeRepository::class);
        $this->standRepositoryMock = $this->createMock(StandRepository::class);
        $this->noteRepositoryMock = $this->createMock(NoteRepository::class);
        $this->command = new DelNoteCommand(
            $this->bikeRepositoryMock,
            $this->standRepositoryMock,
            $this->noteRepositoryMock
        );
    }

    protected function tearDown(): void
    {
        unset(
            $this->bikeRepositoryMock,
            $this->standRepositoryMock,
            $this->noteRepositoryMock,
            $this->command
        );
    }

    public function testInvokeThrowsWhenBikeNotFound(): void
    {
        $this->bikeRepositoryMock->expects($this->once())->method('findItem')->with(123)->willReturn([]);
        $this->standRepositoryMock->expects($this->never())->method('findItemByName');
        $this->noteRepositoryMock->expects($this->never())->method('deleteBikeNote');
        $this->noteRepositoryMock->expects($this->never())->method('deleteStandNote');
        $this->expectException(ValidationException::class);

        ($this->command)($this->createStub(User::class), 123);
    }

    public function testInvokeThrowsWhenNoBikeNotes(): void
    {
        $this->bikeRepositoryMock
            ->expects($this->once())
            ->method('findItem')
            ->with(123)
            ->willReturn(['id' => 1]);
        $this->standRepositoryMock->expects($this->never())->method('findItemByName');
        $this->noteRepositoryMock
            ->expects($this->once())
            ->method('deleteBikeNote')
            ->with(123, null)
            ->willReturn(0);
        $this->noteRepositoryMock->expects($this->never())->method('deleteStandNote');
        $this->expectException(ValidationException::class);

        ($this->command)($this->createStub(User::class), 123);
    }

    public function testInvokeReturnsBikeNotesDeleted(): void
    {
        $this->bikeRepositoryMock
            ->expects($this->once())
            ->method('findItem')
            ->with(123)
            ->willReturn(['id' => 1]);
        $this->standRepositoryMock->expects($this->never())->method('findItemByName');
        $this->noteRepositoryMock
            ->expects($this->once())
            ->method('deleteBikeNote')
            ->with(123, 'pattern')
            ->willReturn(2);
        $this->noteRepositoryMock->expects($this->never())->method('deleteStandNote');

        $result = ($this->command)($this->createStub(User::class), 123, null, 'pattern');

        $this->assertInstanceOf(TranslatableMessage::class, $result);
        $this->assertSame('command.delnote.success_bike', $result->getMessage());
        $this->assertSame(
            [
                'bikeNumber' => 123,
                'count' => 2,
                'hasPattern' => 'true',
                'pattern' => 'pattern',
            ],
            $result->getParameters()
        );
    }

    public function testInvokeThrowsWhenStandInvalid(): void
    {
        $this->bikeRepositoryMock->expects($this->never())->method('findItem');
        $this->standRepositoryMock->expects($this->never())->method('findItemByName');
        $this->noteRepositoryMock->expects($this->never())->method('deleteBikeNote');
        $this->noteRepositoryMock->expects($this->never())->method('deleteStandNote');
        $this->expectException(ValidationException::class);

        ($this->command)($this->createStub(User::class), null, 'stand#1');
    }

    public function testInvokeThrowsWhenStandNotFound(): void
    {
        $this->bikeRepositoryMock->expects($this->never())->method('findItem');
        $this->standRepositoryMock
            ->expects($this->once())
            ->method('findItemByName')
            ->with('STANDX')
            ->willReturn([]);
        $this->noteRepositoryMock->expects($this->never())->method('deleteBikeNote');
        $this->noteRepositoryMock->expects($this->never())->method('deleteStandNote');
        $this->expectException(ValidationException::class);

        ($this->command)($this->createStub(User::class), null, 'STANDX');
    }

    public function testInvokeThrowsWhenNoStandNotes(): void
    {
        $this->bikeRepositoryMock->expects($this->never())->method('findItem');
        $this->standRepositoryMock
            ->expects($this->once())
            ->method('findItemByName')
            ->with('STANDX')
            ->willReturn(['standId' => 1]);
        $this->noteRepositoryMock->expects($this->never())->method('deleteBikeNote');
        $this->noteRepositoryMock
            ->expects($this->once())
            ->method('deleteStandNote')
            ->with(1, null)
            ->willReturn(0);
        $this->expectException(ValidationException::class);

        ($this->command)($this->createStub(User::class), null, 'STANDX');
    }

    public function testInvokeReturnsStandNotesDeleted(): void
    {
        $this->bikeRepositoryMock->expects($this->never())->method('findItem');
        $this->standRepositoryMock
            ->expects($this->once())
            ->method('findItemByName')
            ->with('STANDX')
            ->willReturn(['standId' => 1]);
        $this->noteRepositoryMock->expects($this->never())->method('deleteBikeNote');
        $this->noteRepositoryMock
            ->expects($this->once())
            ->method('deleteStandNote')
            ->with(1, 'pat')
            ->willReturn(3);

        $result = ($this->command)($this->createStub(User::class), null, 'STANDX', 'pat');

        $this->assertInstanceOf(TranslatableMessage::class, $result);
        $this->assertSame('command.delnote.success_stand', $result->getMessage());
        $this->assertSame(
            [
                'standName' => 'STANDX',
                'count' => 3,
                'hasPattern' => 'true',
                'pattern' => 'pat',
            ],
            $result->getParameters()
        );
    }

    public function testInvokeThrowsWhenNoArgs(): void
    {
        $this->bikeRepositoryMock->expects($this->never())->method('findItem');
        $this->standRepositoryMock->expects($this->never())->method('findItemByName');
        $this->noteRepositoryMock->expects($this->never())->method('deleteBikeNote');
        $this->noteRepositoryMock->expects($this->never())->method('deleteStandNote');
        $this->expectException(ValidationException::class);

        ($this->command)($this->createStub(User::class));
    }

    public function testGetHelpMessage(): void
    {
        $this->bikeRepositoryMock->expects($this->never())->method('findItem');
        $this->standRepositoryMock->expects($this->never())->method('findItemByName');
        $this->noteRepositoryMock->expects($this->never())->method('deleteBikeNote');
        $this->noteRepositoryMock->expects($this->never())->method('deleteStandNote');

        $help = $this->command->getHelpMessage();
        $this->assertInstanceOf(TranslatableMessage::class, $help);
        $this->assertSame('command.delnote.help', $help->getMessage());
    }
}
