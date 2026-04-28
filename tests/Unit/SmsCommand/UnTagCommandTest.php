<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\NoteRepository;
use BikeShare\Repository\StandRepository;
use BikeShare\SmsCommand\Exception\ValidationException;
use BikeShare\SmsCommand\UnTagCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatableMessage;

class UnTagCommandTest extends TestCase
{
    private StandRepository&MockObject $standRepositoryMock;
    private NoteRepository&MockObject $noteRepositoryMock;
    private UnTagCommand $command;

    protected function setUp(): void
    {
        $this->standRepositoryMock = $this->createMock(StandRepository::class);
        $this->noteRepositoryMock = $this->createMock(NoteRepository::class);
        $this->command = new UnTagCommand($this->standRepositoryMock, $this->noteRepositoryMock);
    }

    protected function tearDown(): void
    {
        unset($this->standRepositoryMock, $this->noteRepositoryMock, $this->command);
    }

    public function testUnTagStandSuccessNoPattern(): void
    {
        $this->standRepositoryMock
            ->expects($this->once())
            ->method('findItemByName')
            ->with('MAINSQUARE')
            ->willReturn(['standId' => 5]);
        $this->noteRepositoryMock
            ->expects($this->once())
            ->method('deleteNotesForAllBikesOnStand')
            ->with(5, null)
            ->willReturn(3);

        $result = ($this->command)($this->createStub(User::class), 'MAINSQUARE');

        $this->assertInstanceOf(TranslatableMessage::class, $result);
        $this->assertSame('command.untag.success', $result->getMessage());
        $this->assertSame(
            [
                'standName' => 'MAINSQUARE',
                'count' => 3,
                'hasPattern' => 'false',
                'pattern' => '',
            ],
            $result->getParameters()
        );
    }

    public function testUnTagStandSuccessWithPattern(): void
    {
        $this->standRepositoryMock
            ->expects($this->once())
            ->method('findItemByName')
            ->with('MAINSQUARE')
            ->willReturn(['standId' => 5]);
        $this->noteRepositoryMock
            ->expects($this->once())
            ->method('deleteNotesForAllBikesOnStand')
            ->with(5, 'vandalism')
            ->willReturn(2);

        $result = ($this->command)($this->createStub(User::class), 'MAINSQUARE', 'vandalism');

        $this->assertInstanceOf(TranslatableMessage::class, $result);
        $this->assertSame('command.untag.success', $result->getMessage());
        $this->assertSame(
            [
                'standName' => 'MAINSQUARE',
                'count' => 2,
                'hasPattern' => 'true',
                'pattern' => 'vandalism',
            ],
            $result->getParameters()
        );
    }

    public function testUnTagStandInvalidStandNameThrows(): void
    {
        $this->standRepositoryMock->expects($this->never())->method('findItemByName');
        $this->noteRepositoryMock->expects($this->never())->method('deleteNotesForAllBikesOnStand');
        $this->expectException(ValidationException::class);

        ($this->command)($this->createStub(User::class), 'stand#1');
    }

    public function testUnTagStandNotFoundThrows(): void
    {
        $this->standRepositoryMock
            ->expects($this->once())
            ->method('findItemByName')
            ->with('UNKNOWNSTAND')
            ->willReturn(null);
        $this->noteRepositoryMock->expects($this->never())->method('deleteNotesForAllBikesOnStand');
        $this->expectException(ValidationException::class);

        ($this->command)($this->createStub(User::class), 'UNKNOWNSTAND');
    }

    public function testUnTagStandNoNotesFoundThrows(): void
    {
        $this->standRepositoryMock
            ->expects($this->once())
            ->method('findItemByName')
            ->with('MAINSQUARE')
            ->willReturn(['standId' => 5]);
        $this->noteRepositoryMock
            ->expects($this->once())
            ->method('deleteNotesForAllBikesOnStand')
            ->with(5, null)
            ->willReturn(0);
        $this->expectException(ValidationException::class);

        ($this->command)($this->createStub(User::class), 'MAINSQUARE');
    }

    public function testGetHelpMessage(): void
    {
        $this->standRepositoryMock->expects($this->never())->method('findItemByName');
        $this->noteRepositoryMock->expects($this->never())->method('deleteNotesForAllBikesOnStand');

        $help = $this->command->getHelpMessage();
        $this->assertInstanceOf(TranslatableMessage::class, $help);
        $this->assertSame('command.untag.help', $help->getMessage());
    }
}
