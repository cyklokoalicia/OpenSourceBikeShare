<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\NoteRepository;
use BikeShare\SmsCommand\Exception\ValidationException;
use BikeShare\SmsCommand\WhereCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class WhereCommandTest extends TestCase
{
    /** @var TranslatorInterface|MockObject */
    private $translatorMock;
    /** @var BikeRepository|MockObject */
    private $bikeRepositoryMock;
    /** @var NoteRepository|MockObject */
    private $noteRepositoryMock;

    private WhereCommand $command;

    protected function setUp(): void
    {
        $this->translatorMock = $this->createMock(TranslatorInterface::class);
        $this->bikeRepositoryMock = $this->createMock(BikeRepository::class);
        $this->noteRepositoryMock = $this->createMock(NoteRepository::class);

        $this->command = new WhereCommand(
            $this->translatorMock,
            $this->bikeRepositoryMock,
            $this->noteRepositoryMock
        );
    }

    protected function tearDown(): void
    {
        unset(
            $this->translatorMock,
            $this->bikeRepositoryMock,
            $this->noteRepositoryMock,
            $this->command
        );
    }

    public function testBikeAtStand(): void
    {
        $user = $this->createMock(User::class);
        $bikeNumber = 42;

        $this->bikeRepositoryMock
            ->expects($this->once())
            ->method('findItem')
            ->with($bikeNumber)
            ->willReturn(['bikeNumber' => $bikeNumber]);
        $this->noteRepositoryMock
            ->expects($this->once())
            ->method('findBikeNote')
            ->with($bikeNumber)
            ->willReturn([['note' => 'Flat tire']]);
        $this->bikeRepositoryMock
            ->expects($this->once())
            ->method('findBikeCurrentUsage')
            ->with($bikeNumber)
            ->willReturn([
                'number' => '123456789',
                'userName' => null,
                'standName' => 'STAND1',
            ]);
        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with(
                'Bike {bikeNumber} is at stand {standName}. {note}',
                ['bikeNumber' => $bikeNumber, 'standName' => 'STAND1', 'note' => 'Flat tire']
            )
            ->willReturn('Bike 42 is at stand STAND1. Flat tire');

        $this->assertEquals('Bike 42 is at stand STAND1. Flat tire', ($this->command)($user, $bikeNumber));
    }

    public function testBikeRented(): void
    {
        $user = $this->createMock(User::class);
        $bikeNumber = 42;

        $this->bikeRepositoryMock
            ->expects($this->once())
            ->method('findItem')
            ->with($bikeNumber)
            ->willReturn(['bikeNumber' => $bikeNumber]);
        $this->noteRepositoryMock
            ->expects($this->once())
            ->method('findBikeNote')
            ->with($bikeNumber)
            ->willReturn([['note' => 'Broken chain']]);
        $this->bikeRepositoryMock
            ->expects($this->once())
            ->method('findBikeCurrentUsage')
            ->with($bikeNumber)->willReturn([
                'number' => '987654321',
                'userName' => 'John Doe',
                'standName' => null
            ]);
        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with('Bike {bikeNumber} is rented by {userName} (+{phone}). {note}', [
                'bikeNumber' => $bikeNumber,
                'userName' => 'John Doe',
                'phone' => '987654321',
                'note' => 'Broken chain'
            ])
            ->willReturn('Bike 42 is rented by John Doe (+987654321). Broken chain');

        $this->assertEquals(
            'Bike 42 is rented by John Doe (+987654321). Broken chain',
            ($this->command)($user, $bikeNumber)
        );
    }

    public function testBikeNotFoundThrows(): void
    {
        $user = $this->createMock(User::class);
        $bikeNumber = 42;

        $this->bikeRepositoryMock
            ->expects($this->once())
            ->method('findItem')
            ->with($bikeNumber)
            ->willReturn([]);
        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with('Bike {bikeNumber} does not exist.', ['bikeNumber' => $bikeNumber])
            ->willReturn('Bike 42 does not exist.');
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Bike 42 does not exist.');

        ($this->command)($user, $bikeNumber);
    }

    public function testGetHelpMessage(): void
    {
        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with('with bike number: {example}', ['example' => 'WHERE 42'])
            ->willReturn('with bike number: WHERE 42');

        $this->assertEquals('with bike number: WHERE 42', $this->command->getHelpMessage());
    }
}
