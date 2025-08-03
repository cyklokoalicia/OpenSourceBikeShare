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
use Symfony\Contracts\Translation\TranslatorInterface;

class UnTagCommandTest extends TestCase
{
    /** @var TranslatorInterface|MockObject */
    private $translatorMock;
    /** @var StandRepository|MockObject */
    private $standRepositoryMock;
    /** @var NoteRepository|MockObject */
    private $noteRepositoryMock;

    private UnTagCommand $command;

    protected function setUp(): void
    {
        $this->translatorMock = $this->createMock(TranslatorInterface::class);
        $this->standRepositoryMock = $this->createMock(StandRepository::class);
        $this->noteRepositoryMock = $this->createMock(NoteRepository::class);

        $this->command = new UnTagCommand(
            $this->translatorMock,
            $this->standRepositoryMock,
            $this->noteRepositoryMock
        );
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

    public function testUnTagStandSuccessNoPattern(): void
    {
        $user = $this->createMock(User::class);
        $stand = 'MAINSQUARE';

        $this->standRepositoryMock
            ->expects($this->once())
            ->method('findItemByName')
            ->with($stand)
            ->willReturn(['standId' => 5]);
        $this->noteRepositoryMock
            ->expects($this->once())
            ->method('deleteNotesForAllBikesOnStand')
            ->with(5, '')
            ->willReturn(3);
        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with(
                '{count} notes matching pattern "{pattern}" for bikes on stand {standName} were deleted.',
                ['standName' => $stand, 'count' => 3, 'pattern' => '']
            )
            ->willReturn('All 3 notes for bikes on stand MAINSQUARE were deleted.');

        $this->assertEquals(
            'All 3 notes for bikes on stand MAINSQUARE were deleted.',
            ($this->command)($user, $stand, '')
        );
    }

    public function testUnTagStandSuccessWithPattern(): void
    {
        $user = $this->createMock(User::class);
        $stand = 'MAINSQUARE';
        $pattern = 'vandalism';

        $this->standRepositoryMock
            ->expects($this->once())
            ->method('findItemByName')
            ->with($stand)
            ->willReturn(['standId' => 5]);
        $this->noteRepositoryMock
            ->expects($this->once())
            ->method('deleteNotesForAllBikesOnStand')
            ->with(5, $pattern)
            ->willReturn(2);
        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with(
                '{count} notes matching pattern "{pattern}" for bikes on stand {standName} were deleted.',
                ['pattern' => $pattern, 'standName' => $stand, 'count' => 2]
            )
            ->willReturn('2 notes matching pattern "vandalism" for bikes on stand MAINSQUARE were deleted.');

        $this->assertEquals(
            '2 notes matching pattern "vandalism" for bikes on stand MAINSQUARE were deleted.',
            ($this->command)($user, $stand, $pattern)
        );
    }

    public function testUnTagStandInvalidStandNameThrows(): void
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

        ($this->command)($user, $stand);
    }

    public function testUnTagStandNotFoundThrows(): void
    {
        $user = $this->createMock(User::class);
        $stand = 'UNKNOWNSTAND';

        $this->standRepositoryMock->expects($this->once())->method('findItemByName')->with($stand)->willReturn(null);
        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with('Stand {standName} does not exist.', ['standName' => $stand])
            ->willReturn('Stand UNKNOWNSTAND does not exist.');
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Stand UNKNOWNSTAND does not exist.');

        ($this->command)($user, $stand);
    }

    public function testUnTagStandNoNotesFoundThrows(): void
    {
        $user = $this->createMock(User::class);
        $stand = 'MAINSQUARE';

        $this->standRepositoryMock
            ->expects($this->once())
            ->method('findItemByName')
            ->with($stand)
            ->willReturn(['standId' => 5]);
        $this->noteRepositoryMock
            ->expects($this->once())
            ->method('deleteNotesForAllBikesOnStand')
            ->with(5, '')
            ->willReturn(0);
        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with(
                'No notes matching pattern "{pattern}" found for bikes on stand {standName} to delete.',
                ['standName' => $stand, 'pattern' => '']
            )
            ->willReturn('No bikes with notes found for stand MAINSQUARE to delete.');
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('No bikes with notes found for stand MAINSQUARE to delete.');

        ($this->command)($user, $stand, '');
    }

    public function testUnTagStandNoNotesFoundWithPatternThrows(): void
    {
        $user = $this->createMock(User::class);
        $stand = 'MAINSQUARE';
        $pattern = 'vandalism';

        $this->standRepositoryMock
            ->expects($this->once())
            ->method('findItemByName')
            ->with($stand)
            ->willReturn(['standId' => 5]);
        $this->noteRepositoryMock
            ->expects($this->once())
            ->method('deleteNotesForAllBikesOnStand')
            ->with(5, $pattern)
            ->willReturn(0);
        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with(
                'No notes matching pattern "{pattern}" found for bikes on stand {standName} to delete.',
                ['standName' => $stand, 'pattern' => $pattern]
            )
            ->willReturn('No notes matching pattern "vandalism" found for bikes on stand MAINSQUARE to delete.');
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage(
            'No notes matching pattern "vandalism" found for bikes on stand MAINSQUARE to delete.'
        );

        ($this->command)($user, $stand, $pattern);
    }

    public function testGetHelpMessage(): void
    {
        $matcher = $this->exactly(2);
        $this->translatorMock->expects($matcher)
            ->method('trans')
            ->willReturnCallback(
                function (...$parameters) use ($matcher) {
                    if ($matcher->getInvocationCount() === 1) {
                        $this->assertSame('vandalism', $parameters[0]);

                        return 'vandalism';
                    }
                    if ($matcher->getInvocationCount() === 2) {
                        $this->assertSame(
                            'with stand name and optional pattern. '
                                . 'All notes matching pattern will be deleted for all bikes on that stand: {example}',
                            $parameters[0]
                        );
                        $this->assertSame(['example' => 'UNTAG MAINSQUARE vandalism'], $parameters[1]);

                        return 'with stand name and optional pattern. ' .
                            'All notes matching pattern will be deleted for all bikes on that stand: ' .
                            'UNTAG MAINSQUARE vandalism';
                    }
                }
            );

        $this->assertEquals(
            'with stand name and optional pattern. ' .
            'All notes matching pattern will be deleted for all bikes on that stand: UNTAG MAINSQUARE vandalism',
            $this->command->getHelpMessage()
        );
    }
}
