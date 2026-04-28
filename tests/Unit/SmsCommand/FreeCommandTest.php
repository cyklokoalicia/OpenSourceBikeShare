<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\StandRepository;
use BikeShare\SmsCommand\FreeCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatableMessage;

class FreeCommandTest extends TestCase
{
    private BikeRepository&MockObject $bikeRepositoryMock;
    private StandRepository&MockObject $standRepositoryMock;
    private FreeCommand $command;

    protected function setUp(): void
    {
        $this->bikeRepositoryMock = $this->createMock(BikeRepository::class);
        $this->standRepositoryMock = $this->createMock(StandRepository::class);
        $this->command = new FreeCommand($this->bikeRepositoryMock, $this->standRepositoryMock);
    }

    protected function tearDown(): void
    {
        unset($this->bikeRepositoryMock, $this->standRepositoryMock, $this->command);
    }

    public function testInvokeNoBikesNoStands(): void
    {
        $this->bikeRepositoryMock->expects($this->once())->method('findFreeBikes')->willReturn([]);
        $this->standRepositoryMock->expects($this->never())->method('findFreeStands');

        $result = ($this->command)($this->createStub(User::class));

        $this->assertInstanceOf(TranslatableMessage::class, $result);
        $this->assertSame('command.free.message', $result->getMessage());
        $this->assertSame(
            [
                'hasBikes' => 'false',
                'bikesList' => '',
                'hasEmptyStands' => 'false',
                'standsList' => '',
            ],
            $result->getParameters()
        );
    }

    public function testInvokeWithBikesAndEmptyStands(): void
    {
        $this->bikeRepositoryMock
            ->expects($this->once())
            ->method('findFreeBikes')
            ->willReturn([
                ['standName' => 'Main', 'bikeCount' => 3],
                ['standName' => 'Park', 'bikeCount' => 1],
            ]);
        $this->standRepositoryMock
            ->expects($this->once())
            ->method('findFreeStands')
            ->willReturn([['standName' => 'Central'], ['standName' => 'EastEnd']]);

        $result = ($this->command)($this->createStub(User::class));

        $this->assertInstanceOf(TranslatableMessage::class, $result);
        $this->assertSame('command.free.message', $result->getMessage());
        $this->assertSame(
            [
                'hasBikes' => 'true',
                'bikesList' => "Main: 3\nPark: 1",
                'hasEmptyStands' => 'true',
                'standsList' => "Central\nEastEnd",
            ],
            $result->getParameters()
        );
    }

    public function testGetHelpMessage(): void
    {
        $this->bikeRepositoryMock->expects($this->never())->method('findFreeBikes');
        $this->standRepositoryMock->expects($this->never())->method('findFreeStands');

        $help = $this->command->getHelpMessage();
        $this->assertInstanceOf(TranslatableMessage::class, $help);
        $this->assertSame('command.free.help', $help->getMessage());
    }
}
