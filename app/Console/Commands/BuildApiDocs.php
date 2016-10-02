<?php

namespace BikeShare\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class BuildApiDocs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bike-share:docs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Builds api docs based on controller annotations.';


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
    public function handle()
    {
        $docsFile = storage_path('app/api-docs.apib');
        $output = base_path('resources/views/docs/index.blade.php');
        $gulpProcess = new Process('gulp docs');

        $process = new Process("aglio -i {$docsFile} --theme-template triple --no-theme-condense-nav --theme-variables flatly -o {$output}");

        try {
            $gulpProcess->mustRun();
            $process->mustRun();

            $this->info('Documentation view was updated.');
        } catch (ProcessFailedException $e) {
            $this->error($e->getMessage());
        }
    }
}
