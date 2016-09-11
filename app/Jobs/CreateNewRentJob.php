<?php

namespace BikeShare\Jobs;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Rent\Rent;
use BikeShare\Domain\Rent\RentStatus;
use BikeShare\Domain\Stand\Stand;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class CreateNewRentJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $bike;
    protected $oldCode;
    protected $stand;


    /**
     * Create a new job instance.
     *
     * @param Bike $bike
     * @param      $oldCode
     */
    public function __construct(Bike $bike, $oldCode, Stand $stand)
    {
        $this->bike = $bike;
        $this->oldCode = $oldCode;
        $this->stand = $stand;
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
        $rent->standFrom()->associate($this->stand);
        $rent->started_at = Carbon::now();
        $rent->old_code = $this->oldCode;
        $rent->new_code = $this->bike->current_code;

        $rent->save();
    }
}
