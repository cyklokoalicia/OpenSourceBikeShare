<?php

declare(strict_types=1);

namespace BikeShare\App;

use BikeShare\App\DependencyInjection\CreditSystemCompilerPass;
use BikeShare\App\DependencyInjection\MailSenderCompilerPass;
use BikeShare\App\DependencyInjection\RentSystemCompilerPass;
use BikeShare\App\DependencyInjection\SmsConnectorCompilerPass;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $configDir = $this->getConfigDir();

        $container->import($configDir . '/{packages}/*.php');
        $container->import($configDir . '/{packages}/' . $this->environment . '/*.php');

        $container->import($configDir . '/{services}.php');
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new CreditSystemCompilerPass());
        $container->addCompilerPass(new MailSenderCompilerPass());
        $container->addCompilerPass(new RentSystemCompilerPass());
        $container->addCompilerPass(new SmsConnectorCompilerPass());
    }
}
