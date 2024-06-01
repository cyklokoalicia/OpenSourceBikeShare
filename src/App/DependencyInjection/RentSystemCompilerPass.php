<?php

declare(strict_types=1);

namespace BikeShare\App\DependencyInjection;

use BikeShare\Rent\RentSystemFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class RentSystemCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $factory = $container->getDefinition(RentSystemFactory::class);
        $rentSystemServiceIds = $container->findTaggedServiceIds('rentSystem');

        $rentSystems = [];
        foreach ($rentSystemServiceIds as $id => $tags) {
            $rentSystems[$id::getType()] = new Reference($id);
        }

        $factory->setArgument('$locator', ServiceLocatorTagPass::register($container, $rentSystems, 'rentSystems'));
    }
}
