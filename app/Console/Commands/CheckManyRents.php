<?php

namespace BikeShare\Console\Commands;

use BikeShare\Http\Services\Rents\RentService;
use Exception;
use Illuminate\Console\Command;

class CheckManyRents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bike-share:many-rents';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check users to many rents in short time';

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
            app(RentService::class)->checkManyRents();
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
    }
}
