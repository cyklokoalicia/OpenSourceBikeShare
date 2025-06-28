<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Credit\CreditSystemInterface;
use BikeShare\SmsCommand\HelpCommand;
use Generator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class HelpCommandTest extends TestCase
{
    /** @var TranslatorInterface|MockObject */
    private $translatorMock;
    /** @var CreditSystemInterface|MockObject */
    private $creditSystemMock;

    private HelpCommand $command;

    protected function setUp(): void
    {
        $this->translatorMock = $this->createMock(TranslatorInterface::class);
        $this->creditSystemMock = $this->createMock(CreditSystemInterface::class);
        $this->command = new HelpCommand($this->translatorMock, $this->creditSystemMock);
    }

    protected function tearDown(): void
    {
        unset($this->translatorMock, $this->creditSystemMock, $this->command);
    }

    /** @dataProvider invokeDataProvider */
    public function testInvoke(bool $creditSystemCallResult, int $userCallResult, string $message): void
    {
        $userMock = $this->createMock(User::class);

        $this->creditSystemMock->expects($this->once())->method('isEnabled')->willReturn($creditSystemCallResult);
        $userMock->expects($this->once())->method('getPrivileges')->willReturn($userCallResult);

        $this->assertSame($message, ($this->command)($userMock));
    }

    public function invokeDataProvider(): Generator
    {
        yield 'credit system disabled user privileges zero' => [
            'creditSystemCallResult' => false,
            'userCallResult' => 0,
            'message' => implode(PHP_EOL, [
                'Commands:',
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
            'creditSystemCallResult' => true,
            'userCallResult' => 0,
            'message' => implode(PHP_EOL, [
                'Commands:',
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
            'creditSystemCallResult' => true,
            'userCallResult' => 1,
            'message' => implode(PHP_EOL, [
                'Commands:',
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
        $this->assertSame('', $this->command->getHelpMessage());
    }
}
