<?php

declare(strict_types=1);

namespace BikeShare\App\DependencyInjection;

use BikeShare\App\Security\FormLoginAuthenticator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @see https://github.com/symfony/symfony/issues/27961
 */
class FormLoginAuthenticatorPass implements CompilerPassInterface
{
    private const DEFINITION = 'security.authenticator.form_login.main';

    public function process(ContainerBuilder $container): void
    {
        if (false === $container->hasDefinition(self::DEFINITION)) {
            return;
        }

        /** @var ChildDefinition $formLoginFirewall */
        $formLoginFirewall = $container->getDefinition(self::DEFINITION);
        $formLogin = $container->getDefinition($formLoginFirewall->getParent());

        $arguments = array_merge(
            [$formLogin->getArgument(0)],
            array_values($formLoginFirewall->getArguments()),
            [new Reference(RequestStack::class)]
        );

        $container->register(FormLoginAuthenticator::class, FormLoginAuthenticator::class)
            ->setArguments($arguments);

        $container->setAlias(self::DEFINITION, FormLoginAuthenticator::class);
    }
}
