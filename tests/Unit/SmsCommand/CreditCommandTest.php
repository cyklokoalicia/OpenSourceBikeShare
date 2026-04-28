<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Credit\CreditSystemInterface;
use BikeShare\SmsCommand\CreditCommand;
use BikeShare\SmsCommand\Exception\ValidationException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatableMessage;

class CreditCommandTest extends TestCase
{
    private CreditSystemInterface&MockObject $creditSystemMock;
    private CreditCommand $command;

    protected function setUp(): void
    {
        $this->creditSystemMock = $this->createMock(CreditSystemInterface::class);
        $this->command = new CreditCommand($this->creditSystemMock);
    }

    protected function tearDown(): void
    {
        unset($this->creditSystemMock, $this->command);
    }

    public function testInvokeReturnsUserCredit(): void
    {
        $userId = 123;
        $userMock = $this->createMock(User::class);
        $userMock->expects($this->once())->method('getUserId')->willReturn($userId);
        $this->creditSystemMock->expects($this->once())->method('isEnabled')->willReturn(true);
        $this->creditSystemMock->expects($this->once())->method('getUserCredit')->with($userId)->willReturn(15.5);
        $this->creditSystemMock->expects($this->once())->method('getCreditCurrency')->willReturn('EUR');

        $result = ($this->command)($userMock);

        $this->assertInstanceOf(TranslatableMessage::class, $result);
        $this->assertSame('command.credit.message', $result->getMessage());
        $this->assertSame(['credit' => 15.5, 'creditCurrency' => 'EUR'], $result->getParameters());
    }

    public function testInvokeThrowsWhenCreditDisabled(): void
    {
        $this->creditSystemMock->expects($this->once())->method('isEnabled')->willReturn(false);
        $this->creditSystemMock->expects($this->never())->method('getUserCredit');
        $this->expectException(ValidationException::class);

        ($this->command)($this->createStub(User::class));
    }

    public function testGetHelpMessage(): void
    {
        $this->creditSystemMock->expects($this->never())->method('isEnabled');

        $help = $this->command->getHelpMessage();
        $this->assertInstanceOf(TranslatableMessage::class, $help);
        $this->assertSame('command.credit.help', $help->getMessage());
    }
}
