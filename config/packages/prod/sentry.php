<?php

use Symfony\Config\SentryConfig;

return static function (SentryConfig $sentry) {
    $sentry->dsn('%env(SENTRY_DSN)%');
    $sentry->options()
        ->tracesSampleRate(1.0)
        ->attachStacktrace(true);
};
