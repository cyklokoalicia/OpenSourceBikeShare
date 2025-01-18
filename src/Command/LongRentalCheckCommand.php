<?php

declare(strict_types=1);

namespace BikeShare\Command;

use BikeShare\Notifier\AdminNotifier;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\HistoryRepository;
use BikeShare\Sms\SmsSenderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(name: 'app:long_rental_check', description: 'Check user which have long rental')]
class LongRentalCheckCommand extends Command
{
    protected static $defaultName = 'app:long_rental_check';

    private bool $notifyUser;
    private int $longRentalHours;
    private BikeRepository $bikeRepository;
    private HistoryRepository $historyRepository;
    private SmsSenderInterface $smsSender;
    private TranslatorInterface $translator;
    private AdminNotifier $adminNotifier;
    private LoggerInterface $logger;

    public function __construct(
        bool $notifyUser,
        int $longRentalHours,
        BikeRepository $bikeRepository,
        HistoryRepository $historyRepository,
        SmsSenderInterface $smsSender,
        TranslatorInterface $translator,
        AdminNotifier $adminNotifier,
        LoggerInterface $logger
    ) {
        $this->notifyUser = $notifyUser;
        $this->longRentalHours = $longRentalHours;
        $this->bikeRepository = $bikeRepository;
        $this->historyRepository = $historyRepository;
        $this->smsSender = $smsSender;
        $this->translator = $translator;
        $this->adminNotifier = $adminNotifier;
        $this->logger = $logger;
        parent::__construct();
    }

    /**
     * @phpcs:disable Generic.Files.LineLength
     */
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
            $time = strtotime($lastRent['time']);
            if ($time + ($this->longRentalHours * 3600) <= time()) {
                $abusers[] = [
                    'userId' => $userId,
                    'bikeNumber' => $bikeNumber,
                    'userName' => $userName,
                    'userPhone' => $userPhone,
                ];

                if ($this->notifyUser) {
                    $this->smsSender->send(
                        $userPhone,
                        $this->translator->trans(
                            'Please, return your bike {bikeNumber} immediately to the closest stand! Ignoring this warning can get you banned from the system.',
                            ['{bikeNumber}' => $bikeNumber]
                        )
                    );
                }
            }
        }
        if (!empty($abusers)) {
            $message = $this->translator->trans(
                'Bike rental exceed {hour} hours',
                ['hour' => $this->longRentalHours]
            );
            foreach ($abusers as $abuser) {
                $message .= PHP_EOL . 'B' . $abuser['bikeNumber'] . ' '
                    . $abuser['userName'] . ' ' . $abuser['userPhone'];
            }

            $this->adminNotifier->notify($message);
        }

        return Command::SUCCESS;
    }
}
