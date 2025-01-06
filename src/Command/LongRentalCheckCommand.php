<?php

declare(strict_types=1);

namespace BikeShare\Command;

use BikeShare\App\Configuration;
use BikeShare\Event\LongRentEvent;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\HistoryRepository;
use BikeShare\Sms\SmsSenderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(name: 'app:long_rental_check', description: 'Check user which have long rental')]
class LongRentalCheckCommand extends Command
{
    protected static $defaultName = 'app:long_rental_check';

    private bool $notifyUser;
    private BikeRepository $bikeRepository;
    private HistoryRepository $historyRepository;
    private Configuration $configuration;
    private SmsSenderInterface $smsSender;
    private TranslatorInterface $translator;
    private EventDispatcherInterface $eventDispatcher;
    private LoggerInterface $logger;

    public function __construct(
        bool $notifyUser,
        BikeRepository $bikeRepository,
        HistoryRepository $historyRepository,
        Configuration $configuration,
        SmsSenderInterface $smsSender,
        TranslatorInterface $translator,
        EventDispatcherInterface $eventDispatcher,
        LoggerInterface $logger
    ) {
        $this->notifyUser = $notifyUser;
        $this->bikeRepository = $bikeRepository;
        $this->historyRepository = $historyRepository;
        $this->configuration = $configuration;
        $this->smsSender = $smsSender;
        $this->translator = $translator;
        $this->eventDispatcher = $eventDispatcher;
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
            if ($time + ($this->configuration->get('watches')['longrental'] * 3600) <= time()) {
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
            $this->eventDispatcher->dispatch(
                new LongRentEvent($abusers),
                LongRentEvent::NAME
            );
        }

        return Command::SUCCESS;
    }
}
