<?php

declare(strict_types=1);

use Symfony\Config\FrameworkConfig;

return static function (FrameworkConfig $framework): void {
    $framework->phpErrors()
        ->throw(false);

    $framework->session()
        ->storageFactoryId('session.storage.factory.php_bridge')
        ->handlerId(null);

    $cache = $framework->cache();

    $cache->pool('cache.static')
        ->adapters(['cache.adapter.array'])
        ->defaultLifetime(180)
        ->public(true);
};
