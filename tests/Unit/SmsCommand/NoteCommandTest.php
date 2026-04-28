<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\NoteRepository;
use BikeShare\Repository\StandRepository;
use BikeShare\SmsCommand\Exception\ValidationException;
use BikeShare\SmsCommand\NoteCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatableMessage;

class NoteCommandTest extends TestCase
{
    private BikeRepository&MockObject $bikeRepositoryMock;
    private StandRepository&MockObject $standRepositoryMock;
    private NoteRepository&MockObject $noteRepositoryMock;
    private NoteCommand $command;

    protected function setUp(): void
    {
        $this->bikeRepositoryMock = $this->createMock(BikeRepository::class);
        $this->standRepositoryMock = $this->createMock(StandRepository::class);
        $this->noteRepositoryMock = $this->createMock(NoteRepository::class);
        $this->command = new NoteCommand(
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

    public function testAddBikeNoteSuccess(): void
    {
        $userMock = $this->createMock(User::class);
        $userMock->expects($this->once())->method('getUserId')->willReturn(1);
        $bikeNumber = 42;

        $this->bikeRepositoryMock
            ->expects($this->once())
            ->method('findItem')
            ->with($bikeNumber)
            ->willReturn(['bikeNumber' => $bikeNumber]);
        $this->standRepositoryMock->expects($this->never())->method('findItemByName');
        $this->noteRepositoryMock
            ->expects($this->once())
            ->method('addNoteToBike')
            ->with($bikeNumber, 1, 'Flat tire');
        $this->noteRepositoryMock->expects($this->never())->method('addNoteToStand');

        $result = ($this->command)($userMock, $bikeNumber, null, 'Flat tire');

        $this->assertInstanceOf(TranslatableMessage::class, $result);
        $this->assertSame('command.note.success_bike', $result->getMessage());
        $this->assertSame(
            ['note' => 'Flat tire', 'bikeNumber' => $bikeNumber],
            $result->getParameters()
        );
    }

    public function testAddBikeNoteEmptyNoteThrows(): void
    {
        $this->bikeRepositoryMock->expects($this->never())->method('findItem');
        $this->standRepositoryMock->expects($this->never())->method('findItemByName');
        $this->noteRepositoryMock->expects($this->never())->method('addNoteToBike');
        $this->noteRepositoryMock->expects($this->never())->method('addNoteToStand');
        $this->expectException(ValidationException::class);

        ($this->command)($this->createStub(User::class), 42, null, '');
    }

    public function testAddBikeNoteBikeNotFoundThrows(): void
    {
        $this->bikeRepositoryMock->expects($this->once())->method('findItem')->with(99)->willReturn([]);
        $this->standRepositoryMock->expects($this->never())->method('findItemByName');
        $this->noteRepositoryMock->expects($this->never())->method('addNoteToBike');
        $this->noteRepositoryMock->expects($this->never())->method('addNoteToStand');
        $this->expectException(ValidationException::class);

        ($this->command)($this->createStub(User::class), 99, null, 'Broken chain');
    }

    public function testAddStandNoteSuccess(): void
    {
        $userMock = $this->createMock(User::class);
        $userMock->expects($this->once())->method('getUserId')->willReturn(2);

        $this->bikeRepositoryMock->expects($this->never())->method('findItem');
        $this->standRepositoryMock
            ->expects($this->once())
            ->method('findItemByName')
            ->with('STAND1')
            ->willReturn(['standId' => 5]);
        $this->noteRepositoryMock->expects($this->never())->method('addNoteToBike');
        $this->noteRepositoryMock
            ->expects($this->once())
            ->method('addNoteToStand')
            ->with(5, 2, 'Note');

        $result = ($this->command)($userMock, null, 'STAND1', 'Note');

        $this->assertInstanceOf(TranslatableMessage::class, $result);
        $this->assertSame('command.note.success_stand', $result->getMessage());
        $this->assertSame(
            ['note' => 'Note', 'standName' => 'STAND1'],
            $result->getParameters()
        );
    }

    public function testAddStandNoteEmptyNoteThrows(): void
    {
        $this->bikeRepositoryMock->expects($this->never())->method('findItem');
        $this->standRepositoryMock->expects($this->never())->method('findItemByName');
        $this->noteRepositoryMock->expects($this->never())->method('addNoteToBike');
        $this->noteRepositoryMock->expects($this->never())->method('addNoteToStand');
        $this->expectException(ValidationException::class);

        ($this->command)($this->createStub(User::class), null, 'STAND1', '');
    }

    public function testAddStandNoteInvalidStandNameThrows(): void
    {
        $this->bikeRepositoryMock->expects($this->never())->method('findItem');
        $this->standRepositoryMock->expects($this->never())->method('findItemByName');
        $this->noteRepositoryMock->expects($this->never())->method('addNoteToBike');
        $this->noteRepositoryMock->expects($this->never())->method('addNoteToStand');
        $this->expectException(ValidationException::class);

        ($this->command)($this->createStub(User::class), null, 'stand#1', 'Note');
    }

    public function testAddStandNoteStandNotFoundThrows(): void
    {
        $this->bikeRepositoryMock->expects($this->never())->method('findItem');
        $this->standRepositoryMock
            ->expects($this->once())
            ->method('findItemByName')
            ->with('STAND2')
            ->willReturn(null);
        $this->noteRepositoryMock->expects($this->never())->method('addNoteToBike');
        $this->noteRepositoryMock->expects($this->never())->method('addNoteToStand');
        $this->expectException(ValidationException::class);

        ($this->command)($this->createStub(User::class), null, 'STAND2', 'Note');
    }

    public function testInvokeWithNoBikeOrStandThrows(): void
    {
        $this->bikeRepositoryMock->expects($this->never())->method('findItem');
        $this->standRepositoryMock->expects($this->never())->method('findItemByName');
        $this->noteRepositoryMock->expects($this->never())->method('addNoteToBike');
        $this->noteRepositoryMock->expects($this->never())->method('addNoteToStand');
        $this->expectException(ValidationException::class);

        ($this->command)($this->createStub(User::class));
    }

    public function testGetHelpMessage(): void
    {
        $this->bikeRepositoryMock->expects($this->never())->method('findItem');
        $this->standRepositoryMock->expects($this->never())->method('findItemByName');
        $this->noteRepositoryMock->expects($this->never())->method('addNoteToBike');
        $this->noteRepositoryMock->expects($this->never())->method('addNoteToStand');

        $help = $this->command->getHelpMessage();
        $this->assertInstanceOf(TranslatableMessage::class, $help);
        $this->assertSame('command.note.help', $help->getMessage());
    }
}
