<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    // uncomment to reach your current PHP version
    ->withPhpVersion(\Rector\ValueObject\PhpVersion::PHP_74)
    ->withSets([
        \Rector\Set\ValueObject\SetList::PHP_74,
        \Rector\Symfony\Set\SymfonySetList::SYMFONY_54,
        \Rector\Symfony\Set\SymfonySetList::SYMFONY_CODE_QUALITY,
        \Rector\Symfony\Set\SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
    ]);
