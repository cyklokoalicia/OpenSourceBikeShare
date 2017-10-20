<?php

namespace BikeShare\Http\Services\Rents;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Bike\BikeStatus;
use BikeShare\Domain\Stand\Stand;
use BikeShare\Domain\Stand\StandStatus;
use BikeShare\Domain\User\User;
use BikeShare\Http\Services\Rents\Exceptions\BikeNotRentedException;
use BikeShare\Http\Services\Rents\Exceptions\BikeRentedByOtherUserException;
use BikeShare\Http\Services\Rents\Exceptions\NotReturnableStandException;

class ReturnChecks
{

    /**
     * @param User $user
     * @param Bike $bike
     *
     * @throws \BikeShare\Http\Services\Rents\Exceptions\BikeRentedByOtherUserException
     */
    public function bikeRentedByMe(User $user, Bike $bike): void
    {
        if ($bike->user && $bike->user_id != $user->id) {
            throw new BikeRentedByOtherUserException($bike->user);
        }
    }


    /**
     * @param Bike $bike
     *
     * @throws \BikeShare\Http\Services\Rents\Exceptions\BikeNotRentedException
     */
    public function bikeIsRented(Bike $bike): void
    {
        if ($bike->status !== BikeStatus::OCCUPIED) {
            throw new BikeNotRentedException($bike->status);
        }
    }


    /**
     * @param $stand
     *
     * @throws \BikeShare\Http\Services\Rents\Exceptions\NotReturnableStandException
     */
    public function isReturnableStand(Stand $stand): void
    {
        if (! in_array($stand->status, [
            StandStatus::ACTIVE,
            StandStatus::ACTIVE_RETURN_ONLY,
            StandStatus::ACTIVE_SERVICE_RETURN_ONLY,
        ], true)) {
            throw new NotReturnableStandException($stand);
        }
    }
}
