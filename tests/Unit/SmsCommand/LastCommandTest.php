<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\BikeRepository;
use BikeShare\SmsCommand\Exception\ValidationException;
use BikeShare\SmsCommand\LastCommand;
use Generator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class LastCommandTest extends TestCase
{
    /** @var TranslatorInterface|MockObject */
    private $translatorMock;
    /** @var BikeRepository|MockObject */
    private $bikeRepositoryMock;

    private LastCommand $command;

    protected function setUp(): void
    {
        $this->translatorMock = $this->createMock(TranslatorInterface::class);
        $this->bikeRepositoryMock = $this->createMock(BikeRepository::class);
        $this->command = new LastCommand($this->translatorMock, $this->bikeRepositoryMock);
    }

    protected function tearDown(): void
    {
        unset($this->translatorMock, $this->bikeRepositoryMock, $this->command);
    }

    /**
     * @dataProvider invokeDataProvider
     */
    public function testInvoke(array $bikeRepositoryCallResult, string $message): void
    {
        $bikeNumber = 123;
        $userMock = $this->createMock(User::class);

        $this->bikeRepositoryMock
            ->expects($this->once())
            ->method('findItem')
            ->with($bikeNumber)
            ->willReturn(['id' => $bikeNumber]);

        $this->bikeRepositoryMock
            ->expects($this->once())
            ->method('findItemLastUsage')
            ->with($bikeNumber)
            ->willReturn(['history' => $bikeRepositoryCallResult]);

        $this->assertEquals($message, ($this->command)($userMock, $bikeNumber));
    }

    public function testInvokeThrowsWhenBikeInfoEmpty(): void
    {
        $bikeNumber = 123;
        $errorMessage = 'Bike 123 does not exist.';
        $userMock = $this->createMock(User::class);

        $this->bikeRepositoryMock->expects($this->once())->method('findItem')->with($bikeNumber)->willReturn([]);
        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with('Bike {bikeNumber} does not exist.', ['bikeNumber' => $bikeNumber])
            ->willReturn($errorMessage);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage($errorMessage);

        ($this->command)($userMock, $bikeNumber);
    }

    public function testGetHelpMessage(): void
    {
        $message = 'with bike number: LAST 42';

        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with('with bike number: {example}', ['example' => 'LAST 42'])
            ->willReturn($message);

        $this->assertEquals($message, $this->command->getHelpMessage());
    }

    public function invokeDataProvider(): Generator
    {
        yield 'empty history' => [
            'bikeRepositoryCallResult' => [],
            'message' => 'B.123:',
        ];
        yield 'skip history with non-relevant action' => [
            'bikeRepositoryCallResult' => [
                [
                    'action' => 'CHECKUP',
                    'standName' => 'Service Center',
                    'userName' => null,
                    'parameter' => null,
                ]
            ],
            'message' => 'B.123:',
        ];
        yield 'history with RETURN action and stand name' => [
            'bikeRepositoryCallResult' => [
                [
                    'action' => 'RETURN',
                    'standName' => 'Central Station',
                    'userName' => null,
                    'parameter' => null,
                ]
            ],
            'message' => 'B.123:,Central Station',
        ];
        yield 'history with RENT action and stand name' => [
            'bikeRepositoryCallResult' => [
                [
                    'action' => 'RENT',
                    'standName' => 'Main Square',
                    'userName' => null,
                    'parameter' => null,
                ]
            ],
            'message' => 'B.123:,Main Square',
        ];
        yield 'history with REVERT action and stand name' => [
            'bikeRepositoryCallResult' => [
                [
                    'action' => 'REVERT',
                    'standName' => 'Park',
                    'userName' => null,
                    'parameter' => null,
                ]
            ],
            'message' => 'B.123:,*,Park',
        ];
        yield 'complex history with multiple entries' => [
            [
                [
                    'action' => 'RENT',
                    'standName' => 'Central Station',
                    'userName' => null,
                    'parameter' => null,
                ],
                [
                    'action' => 'CHECKUP',
                    'standName' => 'Service',
                    'userName' => null,
                    'parameter' => null,
                ],
                [
                    'action' => 'REVERT',
                    'standName' => 'Main Square',
                    'userName' => null,
                    'parameter' => null,
                ],
                [
                    'action' => 'RETURN',
                    'standName' => null,
                    'userName' => 'Alice',
                    'parameter' => '123',
                ]
            ],
            'B.123:,Central Station,*,Main Square,Alice(123)'
        ];
        yield 'history with null stand name' => [
            'bikeRepositoryCallResult' => [
                [
                    'action' => 'RENT',
                    'standName' => null,
                    'userName' => 'John',
                    'parameter' => 'Quick',
                ]
            ],
            'message' => 'B.123:,John(Quick)',
        ];
    }
}
