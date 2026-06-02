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

    // Persistent (cross-request) pool used to throttle reconfirmation emails: the email
    // checker runs on every authenticated request, so the listener writes a per-user marker
    // here and skips re-sending while it lives. Filesystem-backed; default lifetime = window.
    $cache->pool('cache.reconfirmation_throttle')
        ->adapters(['cache.adapter.filesystem'])
        ->defaultLifetime(900)
        ->public(true);

    $framework
        ->defaultLocale('en')
        ->enabledLocales(['en', 'sk', 'cs', 'de', 'uk'])
        ->translator()
        ->defaultPath('%kernel.project_dir%/translations')
        ->fallbacks(['en']);
};
