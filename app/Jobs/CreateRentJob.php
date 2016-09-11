<?php

namespace BikeShare\Jobs;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Rent\Rent;
use BikeShare\Domain\Rent\RentStatus;
use BikeShare\Domain\Stand\Stand;
use BikeShare\Domain\User\User;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class CreateRentJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $bike;
    protected $old_code;
    protected $started_at;
    protected $standFrom;


    /**
     * Create a new job instance.
     *
     * @param       $user
     * @param Bike  $bike
     * @param       $old_code
     * @param       $started_at
     * @param Stand $standFrom
     */
    public function __construct($user, Bike $bike, $old_code, $started_at, Stand $standFrom)
    {
        $this->user = $user;
        $this->bike = $bike;
        $this->old_code = $old_code;
        $this->started_at = $started_at;
        $this->standFrom = $standFrom;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $rent = new Rent();
        $rent->status = RentStatus::OPEN;
        $rent->user()->associate($this->user);
        $rent->bike()->associate($this->bike);
        $rent->standFrom()->associate($this->standFrom);
        $rent->started_at = $this->started_at;
        $rent->old_code = $this->old_code;
        $rent->new_code = $this->bike->current_code;
        $rent->save();
    }
}
