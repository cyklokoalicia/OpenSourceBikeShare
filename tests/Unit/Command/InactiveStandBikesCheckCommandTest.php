<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\Command;

use BikeShare\Command\InactiveStandBikesCheckCommand;
use BikeShare\Notifier\AdminNotifier;
use BikeShare\Repository\BikeRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Translation\TranslatableMessage;

class InactiveStandBikesCheckCommandTest extends TestCase
{
    private BikeRepository&MockObject $bikeRepository;
    private AdminNotifier&MockObject $adminNotifier;
    private ClockInterface&MockObject $clock;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->bikeRepository = $this->createMock(BikeRepository::class);
        $this->adminNotifier = $this->createMock(AdminNotifier::class);
        $this->clock = $this->createMock(ClockInterface::class);

        $command = new InactiveStandBikesCheckCommand(
            $this->bikeRepository,
            $this->adminNotifier,
            $this->clock
        );
        $this->commandTester = new CommandTester($command);
    }

    public function testExecuteSkipsNotificationWhenNoInactiveBikesAreFound(): void
    {
        $now = new \DateTimeImmutable('2026-02-15 12:00:00');
        $this->clock->expects($this->once())->method('now')->willReturn($now);

        $this->bikeRepository
            ->expects($this->once())
            ->method('findInactiveBikes')
            ->with($this->callback(static fn($time): bool => $time instanceof \DateTimeImmutable
                && $time->format('Y-m-d H:i:s') === '2026-02-08 12:00:00'))
            ->willReturn([]);

        $this->adminNotifier->expects($this->never())->method('notify');

        $this->commandTester->execute([]);
        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        $this->assertStringContainsString(
            'No bikes inactive on non-service stands for more than 7 days.',
            $this->commandTester->getDisplay()
        );
    }

    public function testExecuteSendsEmailNotificationSortedByInactiveDays(): void
    {
        $now = new \DateTimeImmutable('2026-02-15 12:00:00');
        $this->clock->expects($this->once())->method('now')->willReturn($now);

        $this->bikeRepository
            ->expects($this->once())
            ->method('findInactiveBikes')
            ->willReturn(
                [
                    [
                        'bikeNum' => 12,
                        'standName' => 'STAND1',
                        'lastMoveTime' => '2026-02-01 08:00:00',
                    ],
                    [
                        'bikeNum' => 27,
                        'standName' => 'STAND2',
                        'lastMoveTime' => '2026-01-15 10:00:00',
                    ],
                ]
            );

        $notifiedKey = '';
        $notifiedLines = '';
        $this->adminNotifier
            ->expects($this->once())
            ->method('notify')
            ->with($this->isInstanceOf(TranslatableMessage::class), false)
            ->willReturnCallback(
                static function (TranslatableMessage $message, bool $bySms) use (&$notifiedKey, &$notifiedLines): void {
                    $notifiedKey = $message->getMessage();
                    $notifiedLines = $message->getParameters()['lines'] ?? '';
                }
            );

        $this->commandTester->execute([]);
        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());

        $this->assertSame('admin.notification.inactive_bikes', $notifiedKey);
        $this->assertStringContainsString('12 | STAND1 | Last move: 2026-02-01 08:00:00', $notifiedLines);
        $this->assertStringContainsString('27 | STAND2 | Last move: 2026-01-15 10:00:00', $notifiedLines);

        $this->assertLessThan(
            strpos($notifiedLines, '27 | STAND2'),
            strpos($notifiedLines, '12 | STAND1')
        );
        $this->assertSame(1, substr_count($notifiedLines, '27 | STAND2'));

        $this->assertStringContainsString(
            'Admin notification sent for 2 bikes.',
            $this->commandTester->getDisplay()
        );
        $this->assertStringContainsString(
            'Inactive bikes on stands (service stands excluded)',
            $this->commandTester->getDisplay()
        );
        $this->assertStringContainsString('Bike', $this->commandTester->getDisplay());
        $this->assertStringContainsString('12', $this->commandTester->getDisplay());
        $this->assertStringContainsString('27', $this->commandTester->getDisplay());
    }

    public function testExecuteProducesNoOutputInQuietMode(): void
    {
        $now = new \DateTimeImmutable('2026-02-15 12:00:00');
        $this->clock->expects($this->once())->method('now')->willReturn($now);

        $this->bikeRepository
            ->expects($this->once())
            ->method('findInactiveBikes')
            ->willReturn([]);

        $this->adminNotifier->expects($this->never())->method('notify');

        $this->commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_QUIET]);
        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        $this->assertSame('', trim($this->commandTester->getDisplay()));
    }
}
