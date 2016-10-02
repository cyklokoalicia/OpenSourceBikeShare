<?php
namespace BikeShare\Console\Commands;

use Artisan;
use Illuminate\Console\Command;

class ComposerUpdateScript extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'bike-share:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description.';


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
     * @return mixed
     */
    public function fire()
    {
        if ($this->laravel->environment('local')) {
            $this->call('ide-helper:generate');
            $this->call('ide-helper:models', ['--nowrite' => true]);
            $this->call('ide-helper:meta');
        }
    }
}
