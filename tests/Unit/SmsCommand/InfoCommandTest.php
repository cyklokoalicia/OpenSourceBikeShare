<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\StandRepository;
use BikeShare\SmsCommand\Exception\ValidationException;
use BikeShare\SmsCommand\InfoCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatableMessage;

class InfoCommandTest extends TestCase
{
    private StandRepository&MockObject $standRepositoryMock;
    private InfoCommand $command;

    protected function setUp(): void
    {
        $this->standRepositoryMock = $this->createMock(StandRepository::class);
        $this->command = new InfoCommand($this->standRepositoryMock);
    }

    protected function tearDown(): void
    {
        unset($this->standRepositoryMock, $this->command);
    }

    public function testInvoke(): void
    {
        $standName = 'STAND42';
        $this->standRepositoryMock
            ->expects($this->once())
            ->method('findItemByName')
            ->with($standName)
            ->willReturn([
                'standDescription' => 'Near the central park',
                'standPhoto' => 'photo.jpg',
                'latitude' => 1.1,
                'longitude' => 2.2,
            ]);

        $result = ($this->command)($this->createStub(User::class), $standName);

        $this->assertInstanceOf(TranslatableMessage::class, $result);
        $this->assertSame('command.info.message', $result->getMessage());
        $this->assertSame(
            [
                'standName' => 'STAND42',
                'description' => 'Near the central park',
                'hasGps' => 'true',
                'latitude' => 1.1,
                'longitude' => 2.2,
                'hasPhoto' => 'true',
                'photo' => 'photo.jpg',
            ],
            $result->getParameters()
        );
    }

    public function testInvokeThrowsWhenInvalidStandName(): void
    {
        $this->standRepositoryMock->expects($this->never())->method('findItemByName');
        $this->expectException(ValidationException::class);

        ($this->command)($this->createStub(User::class), '123_invalid');
    }

    public function testInvokeThrowsWhenEmptyStandInfo(): void
    {
        $this->standRepositoryMock
            ->expects($this->once())
            ->method('findItemByName')
            ->with('STAND404')
            ->willReturn([]);
        $this->expectException(ValidationException::class);

        ($this->command)($this->createStub(User::class), 'STAND404');
    }

    public function testGetHelpMessage(): void
    {
        $this->standRepositoryMock->expects($this->never())->method('findItemByName');

        $help = $this->command->getHelpMessage();
        $this->assertInstanceOf(TranslatableMessage::class, $help);
        $this->assertSame('command.info.help', $help->getMessage());
    }
}
