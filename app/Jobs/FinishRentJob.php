<?php

namespace BikeShare\Jobs;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Rent\RentStatus;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class FinishRentJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;


    protected $bike;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Bike $bike)
    {
        $this->bike = $bike;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $rent = $this->bike->rents()->where('rents.status', RentStatus::OPEN);
        $rent->standTo()->associate($this->bike->stand);
        $rent->ended_at = Carbon::now();
        $rent->status = RentStatus::CLOSE;
        $rent->save();
    }
}
