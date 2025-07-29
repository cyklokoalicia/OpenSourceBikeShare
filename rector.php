<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    // uncomment to reach your current PHP version
    ->withPhpVersion(\Rector\ValueObject\PhpVersion::PHP_80)
    ->withoutParallel()
    ->withSets([
        \Rector\Set\ValueObject\SetList::PHP_80,
        \Rector\Set\ValueObject\SetList::PHP_81,
    ])->withComposerBased(
        twig: true,
        symfony: true,
        phpunit: true,
    )
    ;
