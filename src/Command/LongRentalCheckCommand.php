<?php

declare(strict_types=1);

namespace BikeShare\Command;

use BikeShare\App\Kernel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:long_rental_check', description: 'Check user which have long rental')]
class LongRentalCheckCommand extends Command
{
    protected static $defaultName = 'app:long_rental_check';

    private Kernel $kernel;

    public function __construct(Kernel $kernel)
    {
        $this->kernel = $kernel;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $kernel = $this->kernel;

        require_once $this->kernel->getContainer()->getParameter('kernel.project_dir') . '/actions-web.php';

        checklongrental();

        return Command::SUCCESS;
    }
}
