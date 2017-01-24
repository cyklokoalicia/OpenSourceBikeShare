<?php

namespace BikeShare\Domain\Rent;

use BikeShare\Domain\Bike\BikeTransformer;
use BikeShare\Domain\Stand\StandTransformer;
use BikeShare\Domain\User\UserTransformer;
use Carbon\Carbon;
use League\Fractal\TransformerAbstract;

class RentTransformer extends TransformerAbstract
{

    /**
     * List of resources possible to include
     *
     * @var array
     */
    protected $availableIncludes = [
        'bike',
        'standFrom',
        'standTo',
        'user',
    ];


    public function transform(Rent $rent)
    {
        return [
            'uuid' => (string)$rent->uuid,
            'status' => $rent->status,
            'old_code' => $rent->old_code,
            'new_code' => $rent->new_code,
            'started_at' => $rent->started_at->toDateTimeString(),
            'ended_at' => $rent->ended_at ? $rent->ended_at->toDateTimeString() : null,
            'duration' => $rent->duration ?? $rent->started_at->diffInSeconds(Carbon::now()),
            'duration_string' => $this->formatDuration($rent->duration, $rent->started_at),
        ];
    }


    public function includeBike(Rent $rent)
    {
        $bike = $rent->bike;

        return $this->item($bike, new BikeTransformer);
    }


    public function includeStandFrom(Rent $rent)
    {
        if ($standFrom = $rent->standFrom) {
            return $this->item($standFrom, new StandTransformer);
        }
    }


    public function includeStandTo(Rent $rent)
    {
        if ($standTo = $rent->standTo) {
            return $this->item($standTo, new StandTransformer);
        }
    }


    public function includeUser(Rent $rent)
    {
        if ($user = $rent->user) {
            return $this->item($user, new UserTransformer());
        }
    }


    protected function formatDuration($duration, $started)
    {
        if ($duration) {
            $H = floor($duration / 3600);
            $i = ($duration / 60) % 60;

            return sprintf("%02dh %02dm", $H, $i);
        }

        return $started->diff(Carbon::now())->format('%Hh %im');
    }
}
