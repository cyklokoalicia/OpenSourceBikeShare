<?php

declare(strict_types=1);

namespace BikeShare\App\DependencyInjection;

use BikeShare\Credit\CreditSystemFactory;
use BikeShare\Credit\CreditSystemInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class CreditSystemCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $factory = $container->getDefinition(CreditSystemFactory::class);
        $creditSystemServiceIds = $container->findTaggedServiceIds('creditSystem');

        $creditSystems = [];
        foreach ($creditSystemServiceIds as $id => $tags) {
            $creditSystems[$id] = new Reference($id);
        }

        $factory->setArgument('$locator', ServiceLocatorTagPass::register($container, $creditSystems, 'creditSystems'));

        $creditSystem = new Definition(CreditSystemInterface::class);
        $creditSystem->setFactory([$factory, 'getCreditSystem']);
        $container->setDefinition(CreditSystemInterface::class, $creditSystem)->setPublic(true);
    }
}
