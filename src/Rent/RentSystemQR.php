<?php

namespace BikeShare\Rent;

use BikeShare\Rent\DTO\RentSystemResult;
use BikeShare\Rent\Enum\RentSystemType;

/**
 * @phpcs:disable Generic.Files.LineLength
 */
class RentSystemQR extends AbstractRentSystem implements RentSystemInterface
{
    public function rentBike($userId, $bikeId, $force = false): RentSystemResult
    {
        $force = false; #rent by qr code cannot be forced

        return parent::rentBike($userId, $bikeId, $force);
    }

    public function returnBike($userId, $bikeId, $standName, $note = '', $force = false): RentSystemResult
    {
        $force = false; #return by qr code cannot be forced
        $note = ''; #note cannot be provided via qr code

        if ($bikeId !== 0) {
            $this->logger->error("Bike number could not be provided via QR code", ["userId" => $userId]);

            return $this->error(
                $this->translator->trans('Invalid bike number'),
                'bike.return.error.invalid_bike_number'
            );
        }

        $rentedBikes = $this->bikeRepository->findRentedBikeNumsByUser($userId);
        $bikeNumber = count($rentedBikes);

        if ($bikeNumber === 0) {
            return $this->error(
                $this->translator->trans('You currently have no rented bikes.'),
                'bike.return.error.no_rented_bikes'
            );
        } elseif ($bikeNumber > 1) {
            $message = $this->translator->trans(
                'You have {bikeNumber} rented bikes currently. QR code return can be used only when 1 bike is rented. Please, use web or SMS to return the bikes.',
                ['bikeNumber' => $bikeNumber]
            );
            if (!$this->isSmsSystemEnabled) {
                $message = $this->translator->trans(
                    'You have {bikeNumber} rented bikes currently. QR code return can be used only when 1 bike is rented. Please, use web to return the bikes.',
                    ['bikeNumber' => $bikeNumber]
                );
            }

            return $this->error(
                $message,
                'bike.return.error.multiple_rented_bikes',
                ['bikeNumber' => $bikeNumber],
            );
        }

        $bikeId = $rentedBikes[0]['bikeNum'];

        return parent::returnBike($userId, $bikeId, $standName, $note, $force);
    }

    public function revertBike($userId, $bikeId): RentSystemResult
    {
        return $this->error(
            $this->translator->trans('Revert is not supported for QR code'),
            'bike.revert.error.not_supported'
        );
    }

    public static function getType(): RentSystemType
    {
        return RentSystemType::QR;
    }
}
