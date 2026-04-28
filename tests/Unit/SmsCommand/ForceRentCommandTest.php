<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Rent\DTO\RentSystemResult;
use BikeShare\Rent\Enum\RentSystemType;
use BikeShare\Rent\RentSystemInterface;
use BikeShare\SmsCommand\ForceRentCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatableMessage;

class ForceRentCommandTest extends TestCase
{
    private RentSystemInterface&MockObject $rentSystemMock;
    private ForceRentCommand $command;

    protected function setUp(): void
    {
        $this->rentSystemMock = $this->createMock(RentSystemInterface::class);
        $this->command = new ForceRentCommand($this->rentSystemMock);
    }

    protected function tearDown(): void
    {
        unset($this->rentSystemMock, $this->command);
    }

    public function testInvoke(): void
    {
        $userMock = $this->createMock(User::class);
        $userId = 123;
        $bikeNumber = 456;
        $expected = new RentSystemResult(
            false,
            'bike.rent.success',
            RentSystemType::SMS,
            ['bikeNumber' => $bikeNumber]
        );

        $userMock->expects($this->once())->method('getUserId')->willReturn($userId);
        $this->rentSystemMock
            ->expects($this->once())
            ->method('rentBike')
            ->with($userId, $bikeNumber, true)
            ->willReturn($expected);

        $this->assertSame($expected, ($this->command)($userMock, $bikeNumber));
    }

    public function testGetHelpMessage(): void
    {
        $this->rentSystemMock->expects($this->never())->method('rentBike');
        $help = $this->command->getHelpMessage();

        $this->assertInstanceOf(TranslatableMessage::class, $help);
        $this->assertSame('command.force_rent.help', $help->getMessage());
    }
}
