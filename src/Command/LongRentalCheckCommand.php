<?php

declare(strict_types=1);

namespace BikeShare\Command;

use BikeShare\Notifier\AdminNotifier;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\HistoryRepository;
use BikeShare\Repository\UserSettingsRepository;
use BikeShare\Sms\SmsSenderInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Translation\TranslatableMessage;

#[AsCommand(name: 'app:long_rental_check', description: 'Check user which have long rental')]
class LongRentalCheckCommand extends Command
{
    public function __construct(
        private readonly bool $notifyUser,
        private readonly int $longRentalHours,
        private readonly BikeRepository $bikeRepository,
        private readonly HistoryRepository $historyRepository,
        private readonly SmsSenderInterface $smsSender,
        private readonly AdminNotifier $adminNotifier,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
        private readonly UserSettingsRepository $userSettingsRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $abusers = [];
        $rentedBikes = $this->bikeRepository->findRentedBikes();
        foreach ($rentedBikes as $row) {
            $bikeNumber = (int)$row['bikeNum'];
            $userId = (int)$row['userId'];
            $userName = $row['userName'];
            $userPhone = $row['number'];

            $lastRent = $this->historyRepository->findLastBikeRentByUser($bikeNumber, $userId);
            if (is_null($lastRent)) {
                $this->logger->error('Last rent not found for bike', compact('bikeNumber', 'userId'));
                continue;
            }

            $time = new \DateTimeImmutable((string) $lastRent['time']);
            if ($time->getTimestamp() + ($this->longRentalHours * 3600) <= $this->clock->now()->getTimestamp()) {
                $abusers[] = [
                    'userId' => $userId,
                    'bikeNumber' => $bikeNumber,
                    'userName' => $userName,
                    'userPhone' => $userPhone,
                ];

                if ($this->notifyUser) {
                    $userLocale = $this->userSettingsRepository->findByUserId($userId)['locale'] ?? null;
                    $this->smsSender->send(
                        $userPhone,
                        new TranslatableMessage(
                            'admin.notification.long_rental_user_warning',
                            ['bikeNumber' => $bikeNumber]
                        ),
                        $userLocale
                    );
                }
            }
        }
        if (!empty($abusers)) {
            $abuserLines = [];
            foreach ($abusers as $abuser) {
                $abuserLines[] = 'B' . $abuser['bikeNumber'] . ' '
                    . $abuser['userName'] . ' ' . $abuser['userPhone'];
            }

            $this->adminNotifier->notify(
                new TranslatableMessage(
                    'admin.notification.long_rental',
                    ['hour' => $this->longRentalHours, 'abusers' => implode(PHP_EOL, $abuserLines)]
                )
            );
        }

        return Command::SUCCESS;
    }
}
