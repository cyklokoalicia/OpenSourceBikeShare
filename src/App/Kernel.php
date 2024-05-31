<?php

declare(strict_types=1);

namespace BikeShare\App;

use BikeShare\App\DependencyInjection\CreditSystemCompilerPass;
use BikeShare\App\DependencyInjection\MailSenderCompilerPass;
use BikeShare\App\DependencyInjection\RentSystemCompilerPass;
use BikeShare\App\DependencyInjection\SmsConnectorCompilerPass;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): array
    {
        return [
            new FrameworkBundle(),
            new MonologBundle(),
        ];
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new CreditSystemCompilerPass());
        $container->addCompilerPass(new MailSenderCompilerPass());
        $container->addCompilerPass(new RentSystemCompilerPass());
        $container->addCompilerPass(new SmsConnectorCompilerPass());
    }
}
