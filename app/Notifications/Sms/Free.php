<?php

namespace BikeShare\Notifications\Sms;

use BikeShare\Domain\Stand\StandsRepository;
use BikeShare\Notifications\SmsNotification;

class Free extends SmsNotification
{
    /**
     * @var StandsRepository
     */
    private $repo;

    public function __construct(StandsRepository $repo)
    {
        $this->repo = $repo;
    }

    public function smsText()
    {
        $stands = $this->repo
            ->withCount('bikes as bikes_count')
            ->orderBy('name')
            ->all();

        list($withBikes, $withoutBikes) = $stands->partition(function ($stand){
            return $stand->bikes_count > 0;
        });

        $withBikes = $withBikes->map(function ($s){
            return "{$s->name}:{$s->bikes_count}";
        })->implode(',');

        $msg = empty($withBikes) ? "No free bikes." : $withBikes;

        if (!empty($withoutBikes)){
            $msg .= " Empty stands:" . $withoutBikes->pluck('name')->implode(',');
        }

        return $msg;
    }
}
