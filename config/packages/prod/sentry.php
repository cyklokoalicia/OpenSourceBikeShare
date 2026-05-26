<?php

return static function (\Symfony\Config\SentryConfig $sentry) {
    $sentry->dsn('%env(SENTRY_DSN)%');
    $sentry->options()
        ->httpConnectTimeout(2)
        ->httpTimeout(2)
        ->defaultIntegrations(true)
        ->tracesSampler(\BikeShare\Sentry\GbfsTracesSampler::class)
        ->attachStacktrace(true)
        ->ignoreExceptions([
            \Symfony\Component\Security\Core\Exception\AccessDeniedException::class,
            \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        ]);
};
