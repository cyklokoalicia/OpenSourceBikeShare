<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\SmsCommand;

use PHPUnit\Framework\Attributes\DataProvider;
use BikeShare\App\Entity\User;
use BikeShare\Credit\CreditSystemInterface;
use BikeShare\SmsCommand\HelpCommand;
use Generator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatableMessage;

class HelpCommandTest extends TestCase
{
    private CreditSystemInterface&MockObject $creditSystemMock;
    private HelpCommand $command;

    protected function setUp(): void
    {
        $this->creditSystemMock = $this->createMock(CreditSystemInterface::class);
        $this->command = new HelpCommand($this->creditSystemMock);
    }

    protected function tearDown(): void
    {
        unset($this->creditSystemMock, $this->command);
    }

    #[DataProvider('invokeDataProvider')]
    public function testInvoke(bool $creditEnabled, int $privileges, string $expectedCommands): void
    {
        $userMock = $this->createMock(User::class);
        $userMock->expects($this->once())->method('getPrivileges')->willReturn($privileges);
        $this->creditSystemMock->expects($this->once())->method('isEnabled')->willReturn($creditEnabled);

        $result = ($this->command)($userMock);

        $this->assertInstanceOf(TranslatableMessage::class, $result);
        $this->assertSame('command.help.message', $result->getMessage());
        $this->assertSame(['commands' => $expectedCommands], $result->getParameters());
    }

    public static function invokeDataProvider(): Generator
    {
        yield 'credit system disabled user privileges zero' => [
            'creditEnabled' => false,
            'privileges' => 0,
            'expectedCommands' => implode("\n", [
                'HELP',
                'FREE',
                'RENT bikeNumber',
                'RETURN bikeNumber standName',
                'WHERE bikeNumber',
                'INFO standName',
                'NOTE bikeNumber problem',
                'NOTE standName problem',
            ]),
        ];
        yield 'credit system enabled user privileges zero' => [
            'creditEnabled' => true,
            'privileges' => 0,
            'expectedCommands' => implode("\n", [
                'HELP',
                'CREDIT',
                'FREE',
                'RENT bikeNumber',
                'RETURN bikeNumber standName',
                'WHERE bikeNumber',
                'INFO standName',
                'NOTE bikeNumber problem',
                'NOTE standName problem',
            ]),
        ];
        yield 'credit system enabled user privileges greater zero' => [
            'creditEnabled' => true,
            'privileges' => 1,
            'expectedCommands' => implode("\n", [
                'HELP',
                'CREDIT',
                'FREE',
                'RENT bikeNumber',
                'RETURN bikeNumber standName',
                'WHERE bikeNumber',
                'INFO standName',
                'NOTE bikeNumber problem',
                'NOTE standName problem',
                'FORCERENT bikeNumber',
                'FORCERETURN bikeNumber standName',
                'LIST standName',
                'LAST bikeNumber',
                'REVERT bikeNumber',
                'CODE bikeNumber code',
                'ADD email phone fullname',
                'DELNOTE bikeNumber [pattern]',
                'DELNOTE standName [pattern]',
                'TAG standName note for all bikes',
                'UNTAG standName [pattern]',
            ]),
        ];
    }

    public function testGetHelpMessage(): void
    {
        $this->creditSystemMock->expects($this->never())->method('isEnabled');

        $help = $this->command->getHelpMessage();
        $this->assertInstanceOf(TranslatableMessage::class, $help);
        $this->assertSame('command.help.help', $help->getMessage());
    }
}
