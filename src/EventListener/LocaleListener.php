<?php

namespace BikeShare\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

#[AsEventListener(priority: 110)]
class LocaleListener
{
    public function __construct(
        private readonly string $defaultLocale,
        private readonly array $enabledLocales,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $locale = $request->getPreferredLanguage($this->enabledLocales) ?? $this->defaultLocale;
        // No session when the request failed before it was opened, e.g. the error page for an untrusted host.
        if ($request->hasSession()) {
            $locale = $request->getSession()->get('_locale', $locale);
        }

        $request->setLocale($locale);
    }
}
