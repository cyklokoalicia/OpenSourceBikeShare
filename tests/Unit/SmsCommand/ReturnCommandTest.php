<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Rent\RentSystemInterface;
use BikeShare\SmsCommand\ReturnCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class ReturnCommandTest extends TestCase
{
    /** @var TranslatorInterface|MockObject */
    private $translatorMock;
    /** @var RentSystemInterface|MockObject */
    private $rentSystemMock;

    private ReturnCommand $command;

    protected function setUp(): void
    {
        $this->translatorMock = $this->createMock(TranslatorInterface::class);
        $this->rentSystemMock = $this->createMock(RentSystemInterface::class);
        $this->command = new ReturnCommand($this->translatorMock, $this->rentSystemMock);
    }

    protected function tearDown(): void
    {
        unset($this->translatorMock, $this->rentSystemMock, $this->command);
    }

    public function testInvoke(): void
    {
        $userMock = $this->createMock(User::class);
        $userId = 123;
        $bikeNumber = 456;
        $standName = 'MAIN_SQUARE';
        $note = 'no issues';
        $expectedMessage = 'Bike rented successfully.';

        $userMock->expects($this->once())->method('getUserId')->willReturn($userId);
        $this->rentSystemMock
            ->expects($this->once())
            ->method('returnBike')
            ->with($userId, $bikeNumber, $standName, $note)
            ->willReturn($expectedMessage);

        $this->assertSame($expectedMessage, ($this->command)($userMock, $bikeNumber, $standName, $note));
    }

    public function testGetHelpMessage(): void
    {
        $expectedMessage = 'with bike number: RETURN 42 MAINSQUARE note';
        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with('with bike number: {example}', ['example' => 'RETURN 42 MAINSQUARE note'])
            ->willReturn($expectedMessage);

        $this->assertSame($expectedMessage, $this->command->getHelpMessage());
    }
}
