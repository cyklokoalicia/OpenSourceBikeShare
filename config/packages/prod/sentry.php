<?php

use Symfony\Config\SentryConfig;

return static function (SentryConfig $sentry) {
    $sentry->dsn('%env(SENTRY_DSN)%');
    $sentry->options()
        ->httpConnectTimeout(2)
        ->httpTimeout(2)
        ->defaultIntegrations(true)
        ->tracesSampleRate(1.0)
        ->attachStacktrace(true);
};
