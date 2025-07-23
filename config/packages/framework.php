<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Config\FrameworkConfig;

return static function (FrameworkConfig $framework): void {
    $framework->phpErrors()
        ->throw(false);

    $framework->session()
        ->enabled(true)
        ->handlerId(null)
        ->cookieSecure('auto')
        ->cookieSamesite(Cookie::SAMESITE_LAX)
        ->storageFactoryId('session.storage.factory.native');

    $framework->router()->utf8(true);

    $framework->secret('%env(APP_SECRET)%');

    $cache = $framework->cache();

    $cache->pool('cache.static')
        ->adapters(['cache.adapter.array'])
        ->defaultLifetime(180)
        ->public(true);
};
