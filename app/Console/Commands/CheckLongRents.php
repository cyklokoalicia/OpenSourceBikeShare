<?php

namespace BikeShare\Console\Commands;

use BikeShare\Http\Services\RentService;
use Exception;
use Illuminate\Console\Command;

class CheckLongRents extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bike-share:long-rents';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and notify about long rents';


    /**
     * Create a new command instance.
     *
     */
    public function __construct()
    {
        parent::__construct();
    }


    /**
     * Execute the console command.
     *
     */
    public function handle()
    {
        try {
            app(RentService::class)->checkLongRent();
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
    }
}
