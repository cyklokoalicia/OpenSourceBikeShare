<?php

declare(strict_types=1);

namespace Test\BikeShare\Unit\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\NoteRepository;
use BikeShare\Repository\StandRepository;
use BikeShare\SmsCommand\Exception\ValidationException;
use BikeShare\SmsCommand\NoteCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class NoteCommandTest extends TestCase
{
    /** @var TranslatorInterface|MockObject */
    private $translatorMock;
    /** @var BikeRepository|MockObject */
    private $bikeRepositoryMock;
    /** @var StandRepository|MockObject */
    private $standRepositoryMock;
    /** @var NoteRepository|MockObject */
    private $noteRepositoryMock;

    private NoteCommand $command;

    protected function setUp(): void
    {
        $this->translatorMock = $this->createMock(TranslatorInterface::class);
        $this->bikeRepositoryMock = $this->createMock(BikeRepository::class);
        $this->standRepositoryMock = $this->createMock(StandRepository::class);
        $this->noteRepositoryMock = $this->createMock(NoteRepository::class);

        $this->command = new NoteCommand(
            $this->translatorMock,
            $this->bikeRepositoryMock,
            $this->standRepositoryMock,
            $this->noteRepositoryMock
        );
    }

    protected function tearDown(): void
    {
        unset(
            $this->translatorMock,
            $this->bikeRepositoryMock,
            $this->standRepositoryMock,
            $this->noteRepositoryMock,
            $this->command
        );
    }

    public function testAddBikeNoteSuccess(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getUserId')->willReturn(1);
        $bikeNumber = 42;
        $note = 'Flat tire';

        $this->bikeRepositoryMock
            ->expects($this->once())
            ->method('findItem')
            ->with($bikeNumber)
            ->willReturn(['bikeNumber' => $bikeNumber]);
        $this->noteRepositoryMock->expects($this->once())->method('addNoteToBike')->with($bikeNumber, 1, $note);
        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with('Note "{note}" for bike {bikeNumber} saved.', ['note' => $note, 'bikeNumber' => $bikeNumber])
            ->willReturn('Note "Flat tire" for bike 42 saved.');

        $this->assertEquals('Note "Flat tire" for bike 42 saved.', ($this->command)($user, $bikeNumber, null, $note));
    }

    public function testAddBikeNoteEmptyNoteThrows(): void
    {
        $user = $this->createMock(User::class);
        $bikeNumber = 42;

        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with(
                'Empty note for bike {bikeNumber} not saved, for deleting notes use DELNOTE (for admins).',
                ['bikeNumber' => $bikeNumber]
            )
            ->willReturn('Empty note for bike 42 not saved.');
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Empty note for bike 42 not saved.');

        ($this->command)($user, $bikeNumber, null, '');
    }

    public function testAddBikeNoteBikeNotFoundThrows(): void
    {
        $user = $this->createMock(User::class);
        $bikeNumber = 99;

        $this->bikeRepositoryMock->expects($this->once())->method('findItem')->with($bikeNumber)->willReturn([]);
        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with('Bike {bikeNumber} does not exist.', ['bikeNumber' => $bikeNumber])
            ->willReturn('Bike 99 does not exist.');
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Bike 99 does not exist.');

        ($this->command)($user, $bikeNumber, null, 'Broken chain');
    }

    public function testAddStandNoteSuccess(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getUserId')->willReturn(2);
        $stand = 'STAND1';
        $note = 'No bikes available';

        $this->standRepositoryMock
            ->expects($this->once())
            ->method('findItemByName')
            ->with($stand)
            ->willReturn(['standId' => 5]);
        $this->noteRepositoryMock
            ->expects($this->once())
            ->method('addNoteToStand')
            ->with(5, 2, $note);
        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with('Note "{note}" for stand {standName} saved.', ['note' => $note, 'standName' => $stand])
            ->willReturn('Note "No bikes available" for stand STAND1 saved.');

        $this->assertEquals(
            'Note "No bikes available" for stand STAND1 saved.',
            ($this->command)($user, null, $stand, $note),
        );
    }

    public function testAddStandNoteEmptyNoteThrows(): void
    {
        $user = $this->createMock(User::class);
        $stand = 'STAND1';

        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with(
                'Empty note for stand {standName} not saved, for deleting notes use DELNOTE (for admins).',
                ['standName' => $stand]
            )
            ->willReturn('Empty note for stand STAND1 not saved.');
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Empty note for stand STAND1 not saved.');

        ($this->command)($user, null, $stand, '');
    }

    public function testAddStandNoteInvalidStandNameThrows(): void
    {
        $user = $this->createMock(User::class);
        $stand = 'stand#1';

        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with(
                'Stand name {standName} has not been recognized. Stands are marked by CAPITALLETTERS.',
                ['standName' => $stand]
            )
            ->willReturn('Stand name stand#1 has not been recognized.');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Stand name stand#1 has not been recognized.');

        ($this->command)($user, null, $stand, 'Something wrong');
    }

    public function testAddStandNoteStandNotFoundThrows(): void
    {
        $user = $this->createMock(User::class);
        $stand = 'STAND2';

        $this->standRepositoryMock->expects($this->once())->method('findItemByName')->with($stand)->willReturn(null);
        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with('Stand {standName} does not exist.', ['standName' => $stand])
            ->willReturn('Stand STAND2 does not exist.');
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Stand STAND2 does not exist.');

        ($this->command)($user, null, $stand, 'No lock');
    }

    public function testInvokeWithNoBikeOrStandThrows(): void
    {
        $user = $this->createMock(User::class);

        $this->translatorMock
            ->expects($this->exactly(2))
            ->method('trans')
            ->withConsecutive(
                ['Flat tire on front wheel'],
                ['with bike number/stand name and problem description: {example}']
            )
            ->willReturnOnConsecutiveCalls('Flat tire on front wheel', 'Help message');
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Help message');

        ($this->command)($user);
    }

    public function testGetHelpMessage(): void
    {
        $this->translatorMock
            ->expects($this->exactly(2))
            ->method('trans')
            ->withConsecutive(
                ['Flat tire on front wheel'],
                [
                    'with bike number/stand name and problem description: {example}',
                    ['example' => 'NOTE 42 Flat tire on front wheel']
                ]
            )
            ->willReturnOnConsecutiveCalls(
                'Flat tire on front wheel',
                'with bike number/stand name and problem description: NOTE 42 Flat tire on front wheel'
            );

        $this->assertEquals(
            'with bike number/stand name and problem description: NOTE 42 Flat tire on front wheel',
            $this->command->getHelpMessage()
        );
    }
}
