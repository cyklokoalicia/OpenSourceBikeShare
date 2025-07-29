<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\StandRepository;
use BikeShare\SmsCommand\Exception\ValidationException;
use BikeShare\SmsCommand\ListCommand;
use Generator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class ListCommandTest extends TestCase
{
    /** @var TranslatorInterface|MockObject */
    private $translatorMock;
    /** @var StandRepository|MockObject */
    private $standRepositoryMock;

    protected function setUp(): void
    {
        $this->translatorMock = $this->createMock(TranslatorInterface::class);
        $this->standRepositoryMock = $this->createMock(StandRepository::class);
    }

    protected function tearDown(): void
    {
        unset($this->translatorMock, $this->standRepositoryMock);
    }

    public function testInvokeThrowsWhenStandNameIsInvalid(): void
    {
        $standName = 'safko4zruseny';
        $message = 'Stand name safko4zruseny has not been recognized. Stands are marked by CAPITALLETTERS.';
        $command = new ListCommand($this->translatorMock, $this->standRepositoryMock);

        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with(
                'Stand name {standName} has not been recognized. Stands are marked by CAPITALLETTERS.',
                ['standName' => $standName]
            )
            ->willReturn($message);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage($message);

        ($command)($this->createMock(User::class), $standName);
    }

    public function testInvokeThrowsWhenStandDoesNotExist(): void
    {
        $standName = 'MAINSQUARE';
        $message = 'Stand MAINSQUARE does not exist.';
        $command = new ListCommand($this->translatorMock, $this->standRepositoryMock);

        $this->standRepositoryMock->expects($this->once())->method('findItemByName')->with($standName)->willReturn([]);
        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with('Stand {standName} does not exist.', ['standName' => $standName])
            ->willReturn($message);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage($message);

        ($command)($this->createMock(User::class), $standName);
    }

    /** @dataProvider invokeDataProvider */
    public function testInvoke(
        bool $forceStack,
        int $standRepositoryFindLastReturnedCallAmount,
        ?int $standRepositoryFindLastReturnedCallResult,
        array $standRepositoryFindBikesOnStandCallResult,
        array $translatorCallParams,
        array $translatorCallResult,
        string $message
    ): void {
        $standName = 'ABC123';
        $standId = 123;
        $userMock = $this->createMock(User::class);
        $command = new ListCommand($this->translatorMock, $this->standRepositoryMock, $forceStack);

        $this->standRepositoryMock
            ->expects($this->once())
            ->method('findItemByName')
            ->with($standName)
            ->willReturn(['standId' => $standId]);
        $this->standRepositoryMock
            ->expects($this->exactly($standRepositoryFindLastReturnedCallAmount))
            ->method('findLastReturnedBikeOnStand')
            ->with($standId)
            ->willReturn($standRepositoryFindLastReturnedCallResult);
        $this->standRepositoryMock
            ->expects($this->once())
            ->method('findBikesOnStand')
            ->with($standId)
            ->willReturn($standRepositoryFindBikesOnStandCallResult);
        $matcher = $this->exactly(count($translatorCallParams));
        $this->translatorMock
            ->expects($matcher)
            ->method('trans')
            ->willReturnCallback(function (...$parameters) use ($matcher, $translatorCallParams, $translatorCallResult) {
                $this->assertSame($translatorCallParams[$matcher->getInvocationCount() - 1], $parameters);

                return $translatorCallResult[$matcher->getInvocationCount() - 1];
            });

        $this->assertSame($message, ($command)($userMock, $standName));
    }

    public function testGetHelpMessage(): void
    {
        $message = 'with stand name: LIST MAINSQUARE';
        $command = new ListCommand($this->translatorMock, $this->standRepositoryMock);

        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with('with stand name: {example}', ['example' => 'LIST MAINSQUARE'])
            ->willReturn($message);

        $this->assertEquals($message, $command->getHelpMessage());
    }

    public function invokeDataProvider(): Generator
    {
        yield 'force stack is true and bikesOnStand empty' => [
            'forceStack' => true,
            'standRepositoryFindLastReturnedCallAmount' => 1,
            'standRepositoryFindLastReturnedCallResult' => 456,
            'standRepositoryFindBikesOnStandCallResult' => [],
            'translatorCallParams' => [['Stand {standName} is empty.', ['standName' => 'ABC123'], null, null]],
            'translatorCallResult' => ['Stand ABC123 is empty.'],
            'message' => 'Stand ABC123 is empty.',
        ];
        yield 'force stack is true and stackTopBike is not null' => [
            'forceStack' => true,
            'standRepositoryFindLastReturnedCallAmount' => 1,
            'standRepositoryFindLastReturnedCallResult' => 456,
            'standRepositoryFindBikesOnStandCallResult' => [
                ['bikeNum' => 456],
                ['bikeNum' => 789],
                ['bikeNum' => 10],
                ['bikeNum' => 11],
            ],
            'translatorCallParams' => [
                ['(first)', [], null, null],
                [
                    'Bikes on stand {standName}: {bikes}',
                    ['standName' => 'ABC123', 'bikes' => '456 (first), 789, 10, 11'],
                    null,
                    null,
                ],
            ],
            'translatorCallResult' => ['(first)', 'Bikes on stand ABC123: 456 (first), 789, 10, 11'],
            'message' => 'Bikes on stand ABC123: 456 (first), 789, 10, 11',
        ];
        yield 'force stack is true and stackTopBike is null' => [
            'forceStack' => true,
            'standRepositoryFindLastReturnedCallAmount' => 1,
            'standRepositoryFindLastReturnedCallResult' => null,
            'standRepositoryFindBikesOnStandCallResult' => [
                ['bikeNum' => 456],
                ['bikeNum' => 789],
                ['bikeNum' => 10],
                ['bikeNum' => 11],
            ],
            'translatorCallParams' => [
                [
                    'Bikes on stand {standName}: {bikes}',
                    ['standName' => 'ABC123', 'bikes' => '456, 789, 10, 11'],
                    null,
                    null,
                ],
            ],
            'translatorCallResult' => ['Bikes on stand ABC123: 456, 789, 10, 11'],
            'message' => 'Bikes on stand ABC123: 456, 789, 10, 11',
        ];
        yield 'force stack is false and stackTopBike is null' => [
            'forceStack' => false,
            'standRepositoryFindLastReturnedCallAmount' => 0,
            'standRepositoryFindLastReturnedCallResult' => null,
            'standRepositoryFindBikesOnStandCallResult' => [
                ['bikeNum' => 456],
                ['bikeNum' => 789],
                ['bikeNum' => 10],
                ['bikeNum' => 11],
            ],
            'translatorCallParams' => [
                [
                    'Bikes on stand {standName}: {bikes}',
                    ['standName' => 'ABC123', 'bikes' => '456, 789, 10, 11'],
                    null,
                    null,
                ],
            ],
            'translatorCallResult' => ['Bikes on stand ABC123: 456, 789, 10, 11'],
            'message' => 'Bikes on stand ABC123: 456, 789, 10, 11',
        ];
    }
}
