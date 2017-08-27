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
            ->withCount('bikes')
            ->orderBy('name')
            ->all();

        $standsWithBikes = $stands->filter(function ($s){
            return $s->bikes_count > 0;
        })->map(function ($s){
            return "{$s->name}:{$s->bikes_count}";
        })->implode(',');

        $msg = empty($standsWithBikes) ? "No free bikes." : $standsWithBikes;

        $standsWithoutBikes = $stands->filter(function ($s){
            return $s->bikes_count == 0;
        })->pluck('name')->implode(',');

        if (!empty($standsWithoutBikes)){
            $msg .= " Empty stands:{$standsWithoutBikes}";
        }

        return $msg;
    }
}
