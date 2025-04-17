<?php

declare(strict_types=1);

namespace Test\BikeShare\Unit\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Credit\CreditSystemInterface;
use BikeShare\SmsCommand\CreditCommand;
use BikeShare\SmsCommand\Exception\ValidationException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class CreditCommandTest extends TestCase
{
    /** @var TranslatorInterface|MockObject */
    private $translatorMock;
    /** @var CreditSystemInterface|MockObject */
    private $creditSystemMock;

    private CreditCommand $command;

    protected function setUp(): void
    {
        $this->translatorMock = $this->createMock(TranslatorInterface::class);
        $this->creditSystemMock = $this->createMock(CreditSystemInterface::class);

        $this->command = new CreditCommand($this->translatorMock, $this->creditSystemMock);
    }

    protected function tearDown(): void
    {
        unset($this->translatorMock, $this->creditSystemMock, $this->command);
    }

    public function testInvokeReturnsUserCredit(): void
    {
        $userMock = $this->createMock(User::class);
        $userId = 123;
        $expectedMessage = 'Your remaining credit: 15.5 EUR';

        $userMock->expects($this->once())->method('getUserId')->willReturn($userId);
        $this->creditSystemMock->expects($this->once())->method('isEnabled')->willReturn(true);
        $this->creditSystemMock->expects($this->once())->method('getUserCredit')->with($userId)->willReturn(15.5);
        $this->creditSystemMock->expects($this->once())->method('getCreditCurrency')->willReturn('EUR');
        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with('Your remaining credit: {credit}', ['credit' => '15.5EUR'])
            ->willReturn($expectedMessage);

        $this->assertSame($expectedMessage, ($this->command)($userMock));
    }

    public function testInvokeThrowsWhenCreditDisabled(): void
    {
        $userMock = $this->createMock(User::class);
        $expectedMessage = 'Error. The command CREDIT does not exist. If you need help, send: HELP';

        $this->creditSystemMock->expects($this->once())->method('isEnabled')->willReturn(false);
        $this->translatorMock
            ->method('trans')
            ->with(
                'Error. The command {badCommand} does not exist. If you need help, send: {helpCommand}',
                [
                    'badCommand' => 'CREDIT',
                    'helpCommand' => 'HELP'
                ]
            )
            ->willReturn($expectedMessage);
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage($expectedMessage);

        ($this->command)($userMock);
    }

    public function testGetHelpMessage(): void
    {
        $this->assertSame('', $this->command->getHelpMessage());
    }
}
