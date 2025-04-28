<?php

declare(strict_types=1);

namespace Test\BikeShare\Unit\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Rent\RentSystemInterface;
use BikeShare\SmsCommand\ForceReturnCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class ForceReturnCommandTest extends TestCase
{
    /** @var TranslatorInterface|MockObject */
    private $translatorMock;
    /** @var RentSystemInterface|MockObject */
    private $rentSystemMock;

    private ForceReturnCommand $command;

    protected function setUp(): void
    {
        $this->translatorMock = $this->createMock(TranslatorInterface::class);
        $this->rentSystemMock = $this->createMock(RentSystemInterface::class);
        $this->command = new ForceReturnCommand($this->translatorMock, $this->rentSystemMock);
    }

    protected function tearDown(): void
    {
        unset($this->translatorMock, $this->rentSystemMock, $this->command);
    }

    public function testInvokeReturnBikeWithAllArguments(): void
    {
        $userMock = $this->createMock(User::class);
        $userId = 123;
        $bikeNumber = 456;
        $standName = 'MAINSQUARE';
        $note = 'note';
        $expectedMessage = 'Return successful';

        $userMock->expects($this->once())->method('getUserId')->willReturn($userId);
        $this->rentSystemMock
            ->expects($this->once())
            ->method('returnBike')
            ->with($userId, $bikeNumber, $standName, $note, true)
            ->willReturn($expectedMessage);

        $this->assertSame($expectedMessage, ($this->command)($userMock, $bikeNumber, $standName, $note));
    }

    public function testInvokeReturnBikeWithoutNote(): void
    {
        $userMock = $this->createMock(User::class);
        $userId = 123;
        $bikeNumber = 456;
        $standName = 'CENTRALPARK';
        $expectedMessage = 'Returned without note';

        $userMock->expects($this->once())->method('getUserId')->willReturn($userId);
        $this->rentSystemMock
            ->expects($this->once())
            ->method('returnBike')
            ->with($userId, $bikeNumber, $standName, null, true)
            ->willReturn($expectedMessage);

        $this->assertSame($expectedMessage, ($this->command)($userMock, $bikeNumber, $standName));
    }

    public function testGetHelpMessage(): void
    {
        $expectedMessage = 'Translated help text';
        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with('with bike number: {example}', ['example' => 'FORCERETURN 42 MAINSQUARE note'])
            ->willReturn($expectedMessage);

        $this->assertSame($expectedMessage, $this->command->getHelpMessage());
    }
}
