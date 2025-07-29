<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\NoteRepository;
use BikeShare\Repository\StandRepository;
use BikeShare\SmsCommand\DelNoteCommand;
use BikeShare\SmsCommand\Exception\ValidationException;
use Generator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class DelNoteCommandTest extends TestCase
{
    /** @var TranslatorInterface|MockObject */
    private $translatorMock;
    /** @var BikeRepository|MockObject */
    private $bikeRepositoryMock;
    /** @var StandRepository|MockObject */
    private $standRepositoryMock;
    /** @var NoteRepository|MockObject */
    private $noteRepositoryMock;

    private DelNoteCommand $command;

    protected function setUp(): void
    {
        $this->translatorMock = $this->createMock(TranslatorInterface::class);
        $this->bikeRepositoryMock = $this->createMock(BikeRepository::class);
        $this->standRepositoryMock = $this->createMock(StandRepository::class);
        $this->noteRepositoryMock = $this->createMock(NoteRepository::class);
        $this->command = new DelNoteCommand(
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

    /** @dataProvider invokeThrowsWhenBikeIsNullDataProvider */
    public function testInvokeThrowsWhenBikeNumberIsNull(
        array $bikeRepositoryCallResult,
        array $translatorCallParams,
        string $translatorCallResult,
        ?string $pattern,
        array $noteRepositoryCallParams,
        string $message
    ): void {
        $userMock = $this->createMock(User::class);
        $bikeNumber = 123;

        $this->bikeRepositoryMock
            ->expects($this->once())
            ->method('findItem')
            ->with($bikeNumber)
            ->willReturn($bikeRepositoryCallResult);
        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with(...$translatorCallParams)
            ->willReturn($translatorCallResult);
        $this->noteRepositoryMock
            ->expects($this->exactly(count($noteRepositoryCallParams)))
            ->method('deleteBikeNote')
            ->with($bikeNumber, $pattern)
            ->willReturn(0);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage($message);

        ($this->command)($userMock, $bikeNumber, null, $pattern);
    }

    /** @dataProvider invokeThrowsWhenStandNameIsNotNullDataProvider */
    public function testInvokeThrowsWhenStandNameIsNotNull(
        string $standName,
        array $translatorCallParams,
        string $translatorCallResult,
        array $standRepositoryCallParams,
        array $standRepositoryCallResult,
        array $noteRepositoryCallParams,
        ?string $pattern,
        string $message
    ): void {
        $userMock = $this->createMock(User::class);

        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with(...$translatorCallParams)
            ->willReturn($translatorCallResult);
        $this->standRepositoryMock
            ->expects($this->exactly(count($standRepositoryCallParams)))
            ->method('findItemByName')
            ->with(...$standRepositoryCallParams)
            ->willReturn($standRepositoryCallResult);
        $matcher = $this->exactly(count($noteRepositoryCallParams));
        $this->noteRepositoryMock
            ->expects($matcher)
            ->method('deleteStandNote')
            ->willReturnCallback(function (...$parameters) use ($matcher, $noteRepositoryCallParams) {
                $this->assertSame($noteRepositoryCallParams[$matcher->getInvocationCount() - 1], $parameters);
                return 0;
            });

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage($message);

        ($this->command)($userMock, null, $standName, $pattern);
    }

    public function testInvokeThrowsWhenBikeNumberAndStandNameIsNull(): void
    {
        $userMock = $this->createMock(User::class);
        $message = 'exception message';

        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with(
                'with bike number and optional pattern. '
                . 'All messages or notes matching pattern will be deleted: {example}',
                ['example' => 'DELNOTE 42 wheel'],
            )
            ->willReturn($message);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage($message);

        ($this->command)($userMock);
    }

    /** @dataProvider invokeReturnMessageDataProvider */
    public function testInvokeReturnMessage(
        ?int $bikeNumber,
        array $bikeRepositoryCallParams,
        array $noteRepositoryDeleteBikeNoteCallParams,
        array $noteRepositoryDeleteStandNoteCallParams,
        array $standRepositoryCallParams,
        array $translatorCallParams,
        ?string $pattern,
        string $message
    ): void {
        $userMock = $this->createMock(User::class);
        $standName = 'ABC123';

        $this->bikeRepositoryMock
            ->expects($this->exactly(count($bikeRepositoryCallParams)))
            ->method('findItem')
            ->with($bikeNumber)
            ->willReturn(['id' => 123]);
        $matcher = $this->exactly(count($noteRepositoryDeleteBikeNoteCallParams));
        $this->noteRepositoryMock
            ->expects($matcher)
            ->method('deleteBikeNote')
            ->willReturnCallback(function (...$parameters) use ($matcher, $noteRepositoryDeleteBikeNoteCallParams) {
                $this->assertSame($noteRepositoryDeleteBikeNoteCallParams[$matcher->getInvocationCount() - 1], $parameters);
                return 2;
            });
        $matcher = $this->exactly(count($noteRepositoryDeleteStandNoteCallParams));
        $this->noteRepositoryMock
            ->expects($matcher)
            ->method('deleteStandNote')
            ->willReturnCallback(function (...$parameters) use ($matcher, $noteRepositoryDeleteStandNoteCallParams) {
                $this->assertSame($noteRepositoryDeleteStandNoteCallParams[$matcher->getInvocationCount() - 1], $parameters);
                return 2;
            });
        $this->standRepositoryMock
            ->expects($this->exactly(count($standRepositoryCallParams)))
            ->method('findItemByName')
            ->with(...$standRepositoryCallParams)
            ->willReturn(['standId' => 123]);
        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with(...$translatorCallParams)
            ->willReturn($message);

        ($this->command)($userMock, $bikeNumber, $standName, $pattern);
    }

    public function testGetHelpMessage(): void
    {
        $translatedMessage = 'with bike number and optional pattern. '
            . 'All messages or notes matching pattern will be deleted: DELNOTE 42 wheel';

        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with(
                'with bike number and optional pattern. '
                . 'All messages or notes matching pattern will be deleted: {example}',
                ['example' => 'DELNOTE 42 wheel']
            )
            ->willReturn($translatedMessage);

        $this->assertEquals($translatedMessage, $this->command->getHelpMessage());
    }

    public function invokeThrowsWhenBikeIsNullDataProvider(): Generator
    {
        yield 'empty bikeInfo' => [
            'bikeRepositoryCallResult' => [],
            'translatorCallParams' => ['Bike {bikeNumber} does not exist.', ['bikeNumber' => 123], null, null],
            'translatorCallResult' => 'Bike 123 does not exist.',
            'pattern' => null,
            'noteRepositoryCallParams' => [],
            'message' => 'Bike 123 does not exist.',
        ];
        yield 'count is zero and pattern is null' => [
            'bikeRepositoryCallResult' => ['id' => 123],
            'translatorCallParams' => ['No notes found for bike {bikeNumber} to delete.', ['bikeNumber' => 123], null, null],
            'translatorCallResult' => 'No notes found for bike 123 to delete.',
            'pattern' => null,
            'noteRepositoryCallParams' => [[123, null]],
            'message' => 'No notes found for bike 123 to delete.',
        ];
        yield 'count is zero and pattern is not null' => [
            'bikeRepositoryCallResult' => ['id' => 123],
            'translatorCallParams' => [
                'No notes matching pattern {pattern} found for bike {bikeNumber} to delete.',
                ['pattern' => 'abc', 'bikeNumber' => 123],
                null,
                null
            ],
            'translatorCallResult' => 'No notes matching pattern abc found for bike 123 to delete.',
            'pattern' => 'abc',
            'noteRepositoryCallParams' => [[123, 'abc']],
            'message' => 'No notes matching pattern abc found for bike 123 to delete.',
        ];
    }

    public function invokeThrowsWhenStandNameIsNotNullDataProvider(): Generator
    {
        yield 'unrecognized standName' => [
            'standName' => 'SAFKO4ZRUSENY',
            'translatorCallParams' => [
                'Stand name {standName} has not been recognized. Stands are marked by CAPITALLETTERS.',
                ['standName' => 'SAFKO4ZRUSENY'],
                null,
                null,
            ],
            'translatorCallResult' => 'Stand name SAFKO4ZRUSENY has not been recognized. '
                . 'Stands are marked by CAPITALLETTERS.',
            'standRepositoryCallParams' => [],
            'standRepositoryCallResult' => [],
            'noteRepositoryCallParams' => [],
            'pattern' => null,
            'message' => 'Stand name SAFKO4ZRUSENY has not been recognized. Stands are marked by CAPITALLETTERS.',
        ];
        yield 'empty standInfo' => [
            'standName' => 'ABCD1234',
            'translatorCallParams' => ['Stand {standName} does not exist.', ['standName' => 'ABCD1234'], null, null],
            'translatorCallResult' => 'Stand ABCD1234 does not exist.',
            'standRepositoryCallParams' => ['ABCD1234'],
            'standRepositoryCallResult' => [],
            'noteRepositoryCallParams' => [],
            'pattern' => null,
            'message' => 'Stand ABCD1234 does not exist.',
        ];
        yield 'count is zero and pattern is null' => [
            'standName' => 'ABCD1234',
            'translatorCallParams' => ['No notes found for stand {standName} to delete.', ['standName' => 'ABCD1234'], null, null],
            'translatorCallResult' => 'No notes found for stand ABCD1234 to delete.',
            'standRepositoryCallParams' => ['ABCD1234'],
            'standRepositoryCallResult' => ['standId' => '123'],
            'noteRepositoryCallParams' => [[123, null]],
            'pattern' => null,
            'message' => 'No notes found for stand ABCD1234 to delete.',
        ];
        yield 'count is zero and pattern is not null' => [
            'standName' => 'ABCD1234',
            'translatorCallParams' => [
                'No notes matching pattern {pattern} found on stand {standName} to delete.',
                ['pattern' => 'abc', 'standName' => 'ABCD1234'],
                null,
                null,
            ],
            'translatorCallResult' => 'No notes matching pattern abc found on stand ABCD1234 to delete.',
            'standRepositoryCallParams' => ['ABCD1234'],
            'standRepositoryCallResult' => ['standId' => '123'],
            'noteRepositoryCallParams' => [[123, 'abc']],
            'pattern' => 'abc',
            'message' => 'No notes matching pattern abc found on stand ABCD1234 to delete.',
        ];
    }

    public function invokeReturnMessageDataProvider(): Generator
    {
        yield 'bikeNumber is not null and pattern is null' => [
            'bikeNumber' => 123,
            'bikeRepositoryCallParams' => [123],
            'noteRepositoryDeleteBikeNoteCallParams' => [[123, null]],
            'noteRepositoryDeleteStandNoteCallParams' => [],
            'standRepositoryCallParams' => [],
            'translatorCallParams' => [
                'All {count} notes for bike {bikeNumber} were deleted.',
                ['bikeNumber' => 123, 'count' => 2],
                null,
                null,
            ],
            'pattern' => null,
            'message' => 'All 2 notes for bike 123 were deleted.',
        ];
        yield 'bikeNumber is not null and pattern is not null' => [
            'bikeNumber' => 123,
            'bikeRepositoryCallParams' => [123],
            'noteRepositoryDeleteBikeNoteCallParams' => [[123, 'abc']],
            'noteRepositoryDeleteStandNoteCallParams' => [],
            'standRepositoryCallParams' => [],
            'translatorCallParams' => [
                '{count} notes matching pattern "{pattern}" for bike {bikeNumber} were deleted.',
                ['bikeNumber' => 123, 'pattern' => 'abc', 'count' => 2],
                null,
                null,
            ],
            'pattern' => 'abc',
            'message' => 'All 2 notes for bike 123 were deleted.',
        ];
        yield 'bikeNumber is null and standName is not null and pattern is null' => [
            'bikeNumber' => null,
            'bikeRepositoryCallParams' => [],
            'noteRepositoryDeleteBikeNoteCallParams' => [],
            'noteRepositoryDeleteStandNoteCallParams' => [[123, null]],
            'standRepositoryCallParams' => ['ABC123'],
            'translatorCallParams' => [
                'All {count} notes for stand {standName} were deleted.',
                ['standName' => 'ABC123', 'count' => 2],
                null,
                null,
            ],
            'pattern' => null,
            'message' => 'All 2 notes for stand ABC123 were deleted.',
        ];
        yield 'bikeNumber is null and standName is not null and pattern is not null' => [
            'bikeNumber' => null,
            'bikeRepositoryCallParams' => [],
            'noteRepositoryDeleteBikeNoteCallParams' => [],
            'noteRepositoryDeleteStandNoteCallParams' => [[123, 'abc']],
            'standRepositoryCallParams' => ['ABC123'],
            'translatorCallParams' => [
                '{count} notes matching pattern "{pattern}" for stand {standName} were deleted.',
                ['standName' => 'ABC123', 'pattern' => 'abc', 'count' => 2],
                null,
                null,
            ],
            'pattern' => 'abc',
            'message' => '2 notes matching pattern "{pattern}" for stand {standName} were deleted.',
        ];
    }
}
