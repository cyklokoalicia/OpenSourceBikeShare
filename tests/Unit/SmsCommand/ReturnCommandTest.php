<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Rent\DTO\RentSystemResult;
use BikeShare\Rent\Enum\RentSystemType;
use BikeShare\Rent\RentSystemInterface;
use BikeShare\SmsCommand\ReturnCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatableMessage;

class ReturnCommandTest extends TestCase
{
    private RentSystemInterface&MockObject $rentSystemMock;
    private ReturnCommand $command;

    protected function setUp(): void
    {
        $this->rentSystemMock = $this->createMock(RentSystemInterface::class);
        $this->command = new ReturnCommand($this->rentSystemMock);
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
        $standName = 'MAIN_SQUARE';
        $note = 'no issues';
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
            ->with($userId, $bikeNumber, $standName, $note)
            ->willReturn($expected);

        $this->assertSame($expected, ($this->command)($userMock, $bikeNumber, $standName, $note));
    }

    public function testGetHelpMessage(): void
    {
        $this->rentSystemMock->expects($this->never())->method('returnBike');
        $help = $this->command->getHelpMessage();

        $this->assertInstanceOf(TranslatableMessage::class, $help);
        $this->assertSame('command.return.help', $help->getMessage());
    }
}
