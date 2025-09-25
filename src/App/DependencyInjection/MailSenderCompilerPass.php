<?php

declare(strict_types=1);

namespace BikeShare\App\DependencyInjection;

use BikeShare\Mail\MailSenderFactory;
use BikeShare\Mail\MailSenderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class MailSenderCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $factory = $container->getDefinition(MailSenderFactory::class);
        $mailSenderServiceIds = $container->findTaggedServiceIds('mailSender');

        $mailSenders = [];
        foreach (array_keys($mailSenderServiceIds) as $id) {
            $mailSenders[$id] = new Reference($id);
        }

        $factory->setArgument('$locator', ServiceLocatorTagPass::register($container, $mailSenders, 'mailSenders'));

        $mailSender = new Definition(MailSenderInterface::class);
        $mailSender->setFactory([$factory, 'getMailSender']);
        $container->setDefinition(MailSenderInterface::class, $mailSender);
    }
}
