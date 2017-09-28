<?php

namespace BikeShare\Console\Commands;

use BikeShare\Domain\User\User;
use BikeShare\Domain\User\UsersRepository;
use BikeShare\Http\Services\AppConfig;
use BikeShare\Http\Services\Rents\RentService;
use Carbon\Carbon;
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
            RentService::checkManyRents();
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
    }
}
