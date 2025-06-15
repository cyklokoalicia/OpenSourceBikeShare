<?php

declare(strict_types=1);

namespace Test\BikeShare\Unit\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\NoteRepository;
use BikeShare\Repository\StandRepository;
use BikeShare\SmsCommand\Exception\ValidationException;
use BikeShare\SmsCommand\TagCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class TagCommandTest extends TestCase
{
    /** @var TranslatorInterface|MockObject */
    private $translatorMock;
    /** @var StandRepository|MockObject */
    private $standRepositoryMock;
    /** @var NoteRepository|MockObject */
    private $noteRepositoryMock;

    private TagCommand $command;

    protected function setUp(): void
    {
        $this->translatorMock = $this->createMock(TranslatorInterface::class);
        $this->standRepositoryMock = $this->createMock(StandRepository::class);
        $this->noteRepositoryMock = $this->createMock(NoteRepository::class);

        $this->command = new TagCommand($this->translatorMock, $this->standRepositoryMock, $this->noteRepositoryMock);
    }

    protected function tearDown(): void
    {
        unset(
            $this->translatorMock,
            $this->standRepositoryMock,
            $this->noteRepositoryMock,
            $this->command
        );
    }

    public function testTagStandSuccess(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getUserId')->willReturn(10);
        $standName = 'MAINSTAND';
        $note = 'vandalism';

        $this->standRepositoryMock
            ->expects($this->once())
            ->method('findItemByName')
            ->with($standName)
            ->willReturn(['standId' => 5]);
        $this->noteRepositoryMock->expects($this->once())->method('addNoteToAllBikesOnStand')->with(5, 10, $note);
        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with(
                'All bikes on stand {standName} tagged with note "{note}".',
                ['standName' => $standName, 'note' => $note]
            )
            ->willReturn('All bikes on stand MAINSTAND tagged with note "vandalism".');

        $this->assertEquals(
            'All bikes on stand MAINSTAND tagged with note "vandalism".',
            ($this->command)($user, $standName, $note)
        );
    }

    public function testTagStandEmptyNoteThrows(): void
    {
        $user = $this->createMock(User::class);
        $standName = 'MAINSTAND';
        $message = 'Empty tag for stand {standName} not saved, '
            . 'for deleting notes for all bikes on stand use UNTAG (for admins).';

        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with($message, ['standName' => $standName])
            ->willReturn('Empty tag for stand MAINSTAND not saved.');
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Empty tag for stand MAINSTAND not saved.');

        ($this->command)($user, $standName, '');
    }

    public function testTagStandInvalidStandNameThrows(): void
    {
        $user = $this->createMock(User::class);
        $standName = 'stand#1';

        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with(
                'Stand name {standName} has not been recognized. Stands are marked by CAPITALLETTERS.',
                ['standName' => $standName]
            )
            ->willReturn('Stand name stand#1 has not been recognized.');
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Stand name stand#1 has not been recognized.');

        ($this->command)($user, $standName, 'vandalism');
    }

    public function testTagStandNotFoundThrows(): void
    {
        $user = $this->createMock(User::class);
        $standName = 'UNKNOWNSTAND';

        $this->standRepositoryMock
            ->expects($this->once())
            ->method('findItemByName')
            ->with($standName)->willReturn(null);
        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with('Stand {standName} does not exist.', ['standName' => $standName])
            ->willReturn('Stand UNKNOWNSTAND does not exist.');
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Stand UNKNOWNSTAND does not exist.');

        ($this->command)($user, $standName, 'vandalism');
    }

    public function testGetHelpMessage(): void
    {
        $this->translatorMock
            ->expects($this->exactly(2))
            ->method('trans')
            ->withConsecutive(
                ['vandalism'],
                ['with stand name and problem description: {example}', ['example' => 'TAG MAINSQUARE vandalism']],
            )
            ->willReturnOnConsecutiveCalls(
                'vandalism',
                'with stand name and problem description: TAG MAINSQUARE vandalism'
            );

        $this->assertEquals(
            'with stand name and problem description: TAG MAINSQUARE vandalism',
            $this->command->getHelpMessage()
        );
    }
}
