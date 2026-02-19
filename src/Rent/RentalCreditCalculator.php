<?php

declare(strict_types=1);

namespace BikeShare\Rent;

use BikeShare\Credit\CreditSystemInterface;
use BikeShare\Enum\CreditChangeType;
use BikeShare\Repository\HistoryRepository;
use Symfony\Component\Clock\ClockInterface;

/**
 * @phpcs:disable Generic.Files.LineLength
 */
class RentalCreditCalculator
{
    public function __construct(
        private readonly CreditSystemInterface $creditSystem,
        private readonly HistoryRepository $historyRepository,
        private readonly ClockInterface $clock,
        private readonly array $watchesConfig,
    ) {
    }

    public function calculateAndApply(int $bikeNum, int $userId): ?float
    {
        if ($this->creditSystem->isEnabled() === false) {
            return null;
        }

        $startTime = $this->historyRepository->findLastRentTime($bikeNum, $userId);
        if ($startTime === null) {
            return null;
        }

        $endTime = $this->clock->now();
        $timeDiff = $endTime->getTimestamp() - $startTime->getTimestamp();
        $totalCreditChange = 0;

        // if the bike is returned and rented again within 10 minutes, a user will not have new free time.
        $returnTime = $this->historyRepository->findLastReturnTime($bikeNum, $userId);
        if ($returnTime !== null) {
            if (($startTime->getTimestamp() - $returnTime->getTimestamp()) < 10 * 60 && $timeDiff > 5 * 60) {
                $rerentFee = $this->creditSystem->getRentalFee();
                if ($rerentFee > 0) {
                    $this->creditSystem->decreaseCredit($userId, $rerentFee, CreditChangeType::RERENT_PENALTY);
                    $totalCreditChange += $rerentFee;
                }
            }
        }

        $freetime = $this->watchesConfig['freetime'];

        if ($timeDiff > $freetime * 60) {
            $overFreeFee = $this->creditSystem->getRentalFee();
            if ($overFreeFee > 0) {
                $this->creditSystem->decreaseCredit($userId, $overFreeFee, CreditChangeType::OVER_FREE_TIME);
                $totalCreditChange += $overFreeFee;
            }
        }

        if ($freetime == 0) {
            $freetime = 1;
        }

        // for further calculations
        if ($this->creditSystem->getPriceCycle() && $timeDiff > $freetime * 60 * 2) {
            // after first paid period, i.e. freetime*2; if pricecycle enabled
            $temptimediff = $timeDiff - ($freetime * 60 * 2);
            if ($this->creditSystem->getPriceCycle() == 1) { // flat price per cycle
                $cycles = ceil($temptimediff / ($this->watchesConfig['flatpricecycle'] * 60));
                $flatFee = $this->creditSystem->getRentalFee() * $cycles;
                if ($flatFee > 0) {
                    $this->creditSystem->decreaseCredit($userId, $flatFee, CreditChangeType::FLAT_RATE);
                    $totalCreditChange += $flatFee;
                }
            } elseif ($this->creditSystem->getPriceCycle() == 2) { // double price per cycle
                $cycles = ceil($temptimediff / ($this->watchesConfig['doublepricecycle'] * 60));
                $tempcreditrent = $this->creditSystem->getRentalFee();
                for ($i = 1; $i <= $cycles; $i++) {
                    $multiplier = $i;
                    if ($multiplier > $this->watchesConfig['doublepricecyclecap']) {
                        $multiplier = $this->watchesConfig['doublepricecyclecap'];
                    }

                    // exception for rent=1, otherwise square won't work:
                    if ($tempcreditrent == 1) {
                        $tempcreditrent = 2;
                    }

                    $doubleFee = pow($tempcreditrent, $multiplier);
                    if ($doubleFee > 0) {
                        $this->creditSystem->decreaseCredit($userId, $doubleFee, CreditChangeType::DOUBLE_PRICE);
                        $totalCreditChange += $doubleFee;
                    }
                }
            }
        }

        if ($timeDiff > $this->watchesConfig['longrental'] * 3600) {
            $longRentalFee = $this->creditSystem->getLongRentalFee();
            if ($longRentalFee > 0) {
                $this->creditSystem->decreaseCredit($userId, $longRentalFee, CreditChangeType::LONG_RENTAL);
                $totalCreditChange += $longRentalFee;
            }
        }

        return $totalCreditChange > 0 ? $totalCreditChange : null;
    }
}
