<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/config',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPhpVersion(\Rector\ValueObject\PhpVersion::PHP_81)
    ->withSets([
        \Rector\Set\ValueObject\SetList::PHP_81,
        \Rector\Set\ValueObject\SetList::CODE_QUALITY,
        \Rector\Set\ValueObject\SetList::CODING_STYLE,
        \Rector\Set\ValueObject\SetList::PHP_POLYFILLS,
    ])->withComposerBased(
        twig: true,
        symfony: true,
        phpunit: true,
    )
    ;
