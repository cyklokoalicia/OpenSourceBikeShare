<?php

namespace BikeShare\Rent;

use BikeShare\Rent\DTO\RentSystemResult;
use BikeShare\Rent\Enum\RentSystemType;

class RentSystemQR extends AbstractRentSystem implements RentSystemInterface
{
    public function rentBike(int $userId, int $bikeId, bool $force = false): RentSystemResult
    {
        $force = false; #rent by qr code cannot be forced

        return parent::rentBike($userId, $bikeId, $force);
    }

    public function returnBike(
        int $userId,
        int $bikeId,
        string $standName,
        ?string $note = null,
        bool $force = false
    ): RentSystemResult {
        $force = false; #return by qr code cannot be forced
        $note = ''; #note cannot be provided via qr code

        if ($bikeId !== 0) {
            $this->logger->error("Bike number could not be provided via QR code", ["userId" => $userId]);

            return $this->error('bike.return.error.invalid_bike_number');
        }

        $rentedBikedByUser = $this->bikeRepository->findRentedBikesByUserId($userId);
        $countRented = count($rentedBikedByUser);

        if ($countRented === 0) {
            return $this->error('bike.return.error.no_rented_bikes');
        }

        if ($countRented > 1) {
            return $this->error(
                'bike.return.error.multiple_rented_bikes',
                [
                    'bikeNumber' => $countRented,
                    'hasSms' => $this->isSmsSystemEnabled ? 'true' : 'false',
                ]
            );
        }

        $bikeId = $rentedBikedByUser[0]['bikeNum'];

        return parent::returnBike($userId, $bikeId, $standName, $note, $force);
    }

    public function revertBike(int $userId, int $bikeId): RentSystemResult
    {
        return $this->error('bike.revert.error.not_supported');
    }

    public static function getType(): RentSystemType
    {
        return RentSystemType::QR;
    }
}
