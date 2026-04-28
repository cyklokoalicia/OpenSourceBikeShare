<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Rent\DTO\RentSystemResult;
use BikeShare\Rent\Enum\RentSystemType;
use BikeShare\Rent\RentSystemInterface;
use BikeShare\SmsCommand\ForceReturnCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatableMessage;

class ForceReturnCommandTest extends TestCase
{
    private RentSystemInterface&MockObject $rentSystemMock;
    private ForceReturnCommand $command;

    protected function setUp(): void
    {
        $this->rentSystemMock = $this->createMock(RentSystemInterface::class);
        $this->command = new ForceReturnCommand($this->rentSystemMock);
    }

    protected function tearDown(): void
    {
        unset($this->rentSystemMock, $this->command);
    }

    public function testInvokeReturnBikeWithAllArguments(): void
    {
        $userMock = $this->createMock(User::class);
        $userId = 123;
        $bikeNumber = 456;
        $standName = 'MAINSQUARE';
        $note = 'note';
        $expected = new RentSystemResult(
            false,
            'bike.return.success',
            RentSystemType::SMS,
            ['bikeNumber' => $bikeNumber]
        );

        $userMock->expects($this->once())->method('getUserId')->willReturn($userId);
        $this->rentSystemMock
            ->expects($this->once())
            ->method('returnBike')
            ->with($userId, $bikeNumber, $standName, $note, true)
            ->willReturn($expected);

        $this->assertSame($expected, ($this->command)($userMock, $bikeNumber, $standName, $note));
    }

    public function testInvokeReturnBikeWithoutNote(): void
    {
        $userMock = $this->createMock(User::class);
        $userId = 123;
        $bikeNumber = 456;
        $standName = 'CENTRALPARK';
        $expected = new RentSystemResult(
            false,
            'bike.return.success',
            RentSystemType::SMS,
            ['bikeNumber' => $bikeNumber]
        );

        $userMock->expects($this->once())->method('getUserId')->willReturn($userId);
        $this->rentSystemMock
            ->expects($this->once())
            ->method('returnBike')
            ->with($userId, $bikeNumber, $standName, null, true)
            ->willReturn($expected);

        $this->assertSame($expected, ($this->command)($userMock, $bikeNumber, $standName));
    }

    public function testGetHelpMessage(): void
    {
        $this->rentSystemMock->expects($this->never())->method('returnBike');
        $help = $this->command->getHelpMessage();

        $this->assertInstanceOf(TranslatableMessage::class, $help);
        $this->assertSame('command.force_return.help', $help->getMessage());
    }
}
