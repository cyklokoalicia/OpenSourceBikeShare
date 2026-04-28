<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\StandRepository;
use BikeShare\SmsCommand\Exception\ValidationException;
use BikeShare\SmsCommand\ListCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatableMessage;

class ListCommandTest extends TestCase
{
    private StandRepository&MockObject $standRepositoryMock;

    protected function setUp(): void
    {
        $this->standRepositoryMock = $this->createMock(StandRepository::class);
    }

    protected function tearDown(): void
    {
        unset($this->standRepositoryMock);
    }

    public function testInvokeThrowsWhenStandNameIsInvalid(): void
    {
        $command = new ListCommand($this->standRepositoryMock);
        $this->standRepositoryMock->expects($this->never())->method('findItemByName');
        $this->expectException(ValidationException::class);

        ($command)($this->createStub(User::class), 'safko4zruseny');
    }

    public function testInvokeThrowsWhenStandDoesNotExist(): void
    {
        $command = new ListCommand($this->standRepositoryMock);
        $this->standRepositoryMock
            ->expects($this->once())
            ->method('findItemByName')
            ->with('MAINSQUARE')
            ->willReturn([]);
        $this->expectException(ValidationException::class);

        ($command)($this->createStub(User::class), 'MAINSQUARE');
    }

    public function testInvokeReturnsEmptyStand(): void
    {
        $command = new ListCommand($this->standRepositoryMock);
        $standName = 'ABC123';
        $this->standRepositoryMock
            ->expects($this->once())
            ->method('findItemByName')
            ->with($standName)
            ->willReturn(['standId' => 1]);
        $this->standRepositoryMock
            ->expects($this->once())
            ->method('findBikesOnStand')
            ->with(1)
            ->willReturn([]);

        $result = ($command)($this->createStub(User::class), $standName);

        $this->assertInstanceOf(TranslatableMessage::class, $result);
        $this->assertSame('command.list.empty', $result->getMessage());
        $this->assertSame(['standName' => $standName], $result->getParameters());
    }

    public function testInvokeWithForceStackAndFirstBike(): void
    {
        $command = new ListCommand($this->standRepositoryMock, true);
        $standName = 'ABC123';
        $this->standRepositoryMock
            ->expects($this->once())
            ->method('findItemByName')
            ->with($standName)
            ->willReturn(['standId' => 1]);
        $this->standRepositoryMock
            ->expects($this->once())
            ->method('findBikesOnStand')
            ->with(1)
            ->willReturn([['bikeNum' => 456], ['bikeNum' => 789]]);
        $this->standRepositoryMock
            ->expects($this->once())
            ->method('findLastReturnedBikeOnStand')
            ->with(1)
            ->willReturn(456);

        $result = ($command)($this->createStub(User::class), $standName);

        $this->assertInstanceOf(TranslatableMessage::class, $result);
        $this->assertSame('command.list.bikes', $result->getMessage());
        $this->assertSame(
            [
                'standName' => $standName,
                'hasFirstBike' => 'true',
                'firstBike' => 456,
                'otherBikes' => '789',
            ],
            $result->getParameters()
        );
    }

    public function testInvokeWithoutForceStack(): void
    {
        $command = new ListCommand($this->standRepositoryMock);
        $this->standRepositoryMock
            ->expects($this->once())
            ->method('findItemByName')
            ->with('ABC123')
            ->willReturn(['standId' => 1]);
        $this->standRepositoryMock
            ->expects($this->once())
            ->method('findBikesOnStand')
            ->with(1)
            ->willReturn([['bikeNum' => 1], ['bikeNum' => 2]]);
        $this->standRepositoryMock->expects($this->never())->method('findLastReturnedBikeOnStand');

        $result = ($command)($this->createStub(User::class), 'ABC123');

        $this->assertInstanceOf(TranslatableMessage::class, $result);
        $this->assertSame('command.list.bikes', $result->getMessage());
        $this->assertSame(
            [
                'standName' => 'ABC123',
                'hasFirstBike' => 'false',
                'firstBike' => '',
                'otherBikes' => '1, 2',
            ],
            $result->getParameters()
        );
    }

    public function testGetHelpMessage(): void
    {
        $command = new ListCommand($this->standRepositoryMock);
        $this->standRepositoryMock->expects($this->never())->method('findItemByName');

        $help = $command->getHelpMessage();
        $this->assertInstanceOf(TranslatableMessage::class, $help);
        $this->assertSame('command.list.help', $help->getMessage());
    }
}
