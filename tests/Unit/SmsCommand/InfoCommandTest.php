<?php

declare(strict_types=1);

namespace Test\BikeShare\Unit\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\StandRepository;
use BikeShare\SmsCommand\Exception\ValidationException;
use BikeShare\SmsCommand\InfoCommand;
use Generator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class InfoCommandTest extends TestCase
{
    /** @var TranslatorInterface|MockObject */
    private $translatorMock;
    /** @var StandRepository|MockObject */
    private $standRepositoryMock;

    private InfoCommand $command;

    protected function setUp(): void
    {
        $this->translatorMock = $this->createMock(TranslatorInterface::class);
        $this->standRepositoryMock = $this->createMock(StandRepository::class);
        $this->command = new InfoCommand($this->translatorMock, $this->standRepositoryMock);
    }

    protected function tearDown(): void
    {
        unset($this->translatorMock, $this->standRepositoryMock, $this->command);
    }

    /** @dataProvider invokeDataProvider */
    public function testInvoke(float $standLong, float $standLat, string $standPhoto, string $message): void
    {
        $userMock = $this->createMock(User::class);
        $standName = 'STAND42';

        $this->standRepositoryMock
            ->expects($this->once())
            ->method('findItemByName')
            ->with($standName)
            ->willReturn([
                'standDescription' => 'Near the central park',
                'standPhoto' => $standPhoto,
                'latitude' => $standLong,
                'longitude' => $standLat,
            ]);

        $this->assertSame($message, ($this->command)($userMock, $standName));
    }

    public function testInvokeThrowsWhenInvalidStandName(): void
    {
        $userMock = $this->createMock(User::class);
        $standName = '123_invalid';
        $expectedMessage = 'Stand name 123_invalid has not been recognized.';

        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with(
                'Stand name {standName} has not been recognized. Stands are marked by CAPITALLETTERS.',
                ['standName' => $standName]
            )
            ->willReturn($expectedMessage);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage($expectedMessage);

        ($this->command)($userMock, $standName);
    }

    public function testInvokeThrowsWhenEmptyStandInfo(): void
    {
        $userMock = $this->createMock(User::class);
        $standName = 'STAND404';

        $this->standRepositoryMock
            ->expects($this->once())
            ->method('findItemByName')
            ->with($standName)
            ->willReturn([]);

        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with('Stand {standName} does not exist.', ['standName' => $standName])
            ->willReturn('Stand STAND404 does not exist.');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Stand STAND404 does not exist.');

        ($this->command)($userMock, $standName);
    }

    public function invokeDataProvider(): Generator
    {
        yield 'standLong not empty' => [
            'standLong' => 1.1,
            'standLat' => 0.0,
            'standPhoto' => '',
            'message' => 'STAND42 - Near the central park',
        ];
        yield 'standLat not empty' => [
            'standLong' => 0.0,
            'standLat' => 1.1,
            'standPhoto' => '',
            'message' => 'STAND42 - Near the central park',
        ];
        yield 'standPhoto not empty' => [
            'standLong' => 0.0,
            'standLat' => 0.0,
            'standPhoto' => 'stand photo',
            'message' => 'STAND42 - Near the central park, stand photo',
        ];
        yield 'standLong and standLat not empty' => [
            'standLong' => 1.1,
            'standLat' => 1.1,
            'standPhoto' => '',
            'message' => 'STAND42 - Near the central park, GPS: 1.1,1.1',
        ];
        yield 'standLong and standPhoto not empty' => [
            'standLong' => 1.1,
            'standLat' => 0.0,
            'standPhoto' => 'stand photo',
            'message' => 'STAND42 - Near the central park, stand photo',
        ];
        yield 'standLat and standPhoto not empty' => [
            'standLong' => 0.0,
            'standLat' => 1.1,
            'standPhoto' => 'stand photo',
            'message' => 'STAND42 - Near the central park, stand photo',
        ];
        yield 'standLat and standLong and standPhoto not empty' => [
            'standLong' => 1.1,
            'standLat' => 1.1,
            'standPhoto' => 'stand photo',
            'message' => 'STAND42 - Near the central park, GPS: 1.1,1.1, stand photo',
        ];
    }

    public function testGetHelpMessage(): void
    {
        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with('with stand name: {example}', ['example' => 'INFO RACKO'])
            ->willReturn('with stand name: INFO RACKO');

        $this->assertEquals('with stand name: INFO RACKO', $this->command->getHelpMessage());
    }
}
