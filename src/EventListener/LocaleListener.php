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
        $session = $request->getSession();

        $locale = $request->getPreferredLanguage($this->enabledLocales) ?? $this->defaultLocale;

        $locale = $session->get('_locale', $locale);
        $request->setLocale($locale);
    }
}
