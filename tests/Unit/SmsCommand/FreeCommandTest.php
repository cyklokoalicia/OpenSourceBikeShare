<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\StandRepository;
use BikeShare\SmsCommand\FreeCommand;
use Generator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class FreeCommandTest extends TestCase
{
    /** @var TranslatorInterface|MockObject */
    private $translatorMock;
    /** @var BikeRepository|MockObject */
    private $bikeRepositoryMock;
    /** @var StandRepository|MockObject */
    private $standRepositoryMock;

    private FreeCommand $command;

    protected function setUp(): void
    {
        $this->translatorMock = $this->createMock(TranslatorInterface::class);
        $this->bikeRepositoryMock = $this->createMock(BikeRepository::class);
        $this->standRepositoryMock = $this->createMock(StandRepository::class);
        $this->command = new FreeCommand($this->translatorMock, $this->bikeRepositoryMock, $this->standRepositoryMock);
    }

    protected function tearDown(): void
    {
        unset($this->translatorMock, $this->bikeRepositoryMock, $this->standRepositoryMock, $this->command);
    }

    /** @dataProvider invokeDataProvider */
    public function testInvoke(
        array $bikeRepositoryCallResult,
        array $translatorCallParams,
        array $translatorCallResult,
        int $standRepositoryCallsCount,
        array $standRepositoryCallResult,
        string $message
    ): void {
        $user = $this->createMock(User::class);

        $this->bikeRepositoryMock
            ->expects($this->once())
            ->method('findFreeBikes')
            ->willReturn($bikeRepositoryCallResult);
        $this->translatorMock
            ->expects($this->exactly(count($translatorCallParams)))
            ->method('trans')
            ->withConsecutive(...$translatorCallParams)
            ->willReturnOnConsecutiveCalls(...$translatorCallResult);
        $this->standRepositoryMock
            ->expects($this->exactly($standRepositoryCallsCount))
            ->method('findFreeStands')
            ->willReturnOnConsecutiveCalls(...$standRepositoryCallResult);

        $this->assertSame($message, ($this->command)($user));
    }

    public function testGetHelpMessage(): void
    {
        $this->assertSame('', $this->command->getHelpMessage());
    }

    public function invokeDataProvider(): Generator
    {
        yield 'empty free bikes' => [
            'bikeRepositoryCallResult' => [],
            'translatorCallParams' => [
                ['No free bikes.'],
            ],
            'translatorCallResult' => ['No free bikes.'],
            'standRepositoryCallsCount' => 0,
            'standRepositoryCallResult' => [],
            'message' => 'No free bikes.',
        ];
        yield 'empty free stands' => [
            'bikeRepositoryCallResult' => [
                ['standName' => 'Main', 'bikeCount' => 3],
                ['standName' => 'Park', 'bikeCount' => 1],
            ],
            'translatorCallParams' => [
                ['Free bikes counts'],
            ],
            'translatorCallResult' => ['Free bikes counts'],
            'standRepositoryCallsCount' => 1,
            'standRepositoryCallResult' => [[]],
            'message' => implode(PHP_EOL, ['Free bikes counts:', 'Main: 3', 'Park: 1']),
        ];
        yield 'not empty free stands' => [
            'bikeRepositoryCallResult' => [
                ['standName' => 'Main', 'bikeCount' => 3],
                ['standName' => 'Park', 'bikeCount' => 1],
            ],
            'translatorCallParams' => [
                ['Free bikes counts'],
                ['Empty stands'],
            ],
            'translatorCallResult' => ['Free bikes counts', 'Empty stands'],
            'standRepositoryCallsCount' => 1,
            'standRepositoryCallResult' => [
                [['standName' => 'Central'], ['standName' => 'EastEnd']],
            ],
            'message' => implode(PHP_EOL, [
                'Free bikes counts:',
                'Main: 3',
                'Park: 1' . PHP_EOL,
                'Empty stands:',
                'Central',
                'EastEnd'
            ]),
        ];
    }
}
