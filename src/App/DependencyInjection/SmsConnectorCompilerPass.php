<?php

declare(strict_types=1);

namespace BikeShare\App\DependencyInjection;

use BikeShare\SmsConnector\SmsConnectorFactory;
use BikeShare\SmsConnector\SmsConnectorInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class SmsConnectorCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $factory = $container->getDefinition(SmsConnectorFactory::class);
        $smsConnectorServiceIds = $container->findTaggedServiceIds('smsConnector');

        $smsConnectors = [];
        foreach ($smsConnectorServiceIds as $id => $tags) {
            $smsConnectors[$id::getType()] = new Reference($id);
        }

        $factory->setArgument('$locator', ServiceLocatorTagPass::register($container, $smsConnectors, 'smsConnectors'));

        $creditSystem = new Definition(SmsConnectorInterface::class);
        $creditSystem->setFactory([$factory, 'getConnector']);
        $container->setDefinition(SmsConnectorInterface::class, $creditSystem)->setPublic(true);
    }
}
