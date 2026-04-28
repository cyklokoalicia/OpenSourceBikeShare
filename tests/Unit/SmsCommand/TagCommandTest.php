<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\NoteRepository;
use BikeShare\Repository\StandRepository;
use BikeShare\SmsCommand\Exception\ValidationException;
use BikeShare\SmsCommand\TagCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatableMessage;

class TagCommandTest extends TestCase
{
    private StandRepository&MockObject $standRepositoryMock;
    private NoteRepository&MockObject $noteRepositoryMock;
    private TagCommand $command;

    protected function setUp(): void
    {
        $this->standRepositoryMock = $this->createMock(StandRepository::class);
        $this->noteRepositoryMock = $this->createMock(NoteRepository::class);
        $this->command = new TagCommand($this->standRepositoryMock, $this->noteRepositoryMock);
    }

    protected function tearDown(): void
    {
        unset($this->standRepositoryMock, $this->noteRepositoryMock, $this->command);
    }

    public function testTagStandSuccess(): void
    {
        $userMock = $this->createMock(User::class);
        $userMock->expects($this->once())->method('getUserId')->willReturn(10);
        $standName = 'MAINSTAND';
        $note = 'vandalism';

        $this->standRepositoryMock
            ->expects($this->once())
            ->method('findItemByName')
            ->with($standName)
            ->willReturn(['standId' => 5]);
        $this->noteRepositoryMock
            ->expects($this->once())
            ->method('addNoteToAllBikesOnStand')
            ->with(5, 10, $note);

        $result = ($this->command)($userMock, $standName, $note);

        $this->assertInstanceOf(TranslatableMessage::class, $result);
        $this->assertSame('command.tag.success', $result->getMessage());
        $this->assertSame(['standName' => $standName, 'note' => $note], $result->getParameters());
    }

    public function testTagStandEmptyNoteThrows(): void
    {
        $this->standRepositoryMock->expects($this->never())->method('findItemByName');
        $this->noteRepositoryMock->expects($this->never())->method('addNoteToAllBikesOnStand');
        $this->expectException(ValidationException::class);

        ($this->command)($this->createStub(User::class), 'MAINSTAND', '');
    }

    public function testTagStandInvalidStandNameThrows(): void
    {
        $this->standRepositoryMock->expects($this->never())->method('findItemByName');
        $this->noteRepositoryMock->expects($this->never())->method('addNoteToAllBikesOnStand');
        $this->expectException(ValidationException::class);

        ($this->command)($this->createStub(User::class), 'stand#1', 'vandalism');
    }

    public function testTagStandNotFoundThrows(): void
    {
        $this->standRepositoryMock
            ->expects($this->once())
            ->method('findItemByName')
            ->with('UNKNOWNSTAND')
            ->willReturn(null);
        $this->noteRepositoryMock->expects($this->never())->method('addNoteToAllBikesOnStand');
        $this->expectException(ValidationException::class);

        ($this->command)($this->createStub(User::class), 'UNKNOWNSTAND', 'vandalism');
    }

    public function testGetHelpMessage(): void
    {
        $this->standRepositoryMock->expects($this->never())->method('findItemByName');
        $this->noteRepositoryMock->expects($this->never())->method('addNoteToAllBikesOnStand');

        $help = $this->command->getHelpMessage();
        $this->assertInstanceOf(TranslatableMessage::class, $help);
        $this->assertSame('command.tag.help', $help->getMessage());
    }
}
