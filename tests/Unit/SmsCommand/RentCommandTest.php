<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Rent\RentSystemInterface;
use BikeShare\SmsCommand\RentCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class RentCommandTest extends TestCase
{
    /** @var TranslatorInterface|MockObject */
    private $translatorMock;
    /** @var RentSystemInterface|MockObject */
    private $rentSystemMock;

    private RentCommand $command;

    protected function setUp(): void
    {
        $this->translatorMock = $this->createMock(TranslatorInterface::class);
        $this->rentSystemMock = $this->createMock(RentSystemInterface::class);
        $this->command = new RentCommand($this->translatorMock, $this->rentSystemMock);
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
        $expectedMessage = 'Bike rented successfully.';

        $userMock->expects($this->once())->method('getUserId')->willReturn($userId);
        $this->rentSystemMock
            ->expects($this->once())
            ->method('rentBike')
            ->with($userId, $bikeNumber)
            ->willReturn($expectedMessage);

        $this->assertSame($expectedMessage, ($this->command)($userMock, $bikeNumber));
    }

    public function testGetHelpMessage(): void
    {
        $expectedMessage = 'with bike number: RENT 42';
        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with('with bike number: {example}', ['example' => 'RENT 42'])
            ->willReturn($expectedMessage);

        $this->assertSame($expectedMessage, $this->command->getHelpMessage());
    }
}
