<?php

namespace BikeShare\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

#[AsEventListener(priority: 110)]
class LocaleListener
{
    public function __construct(
        private readonly string $defaultLocale = 'en',
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $session = $request->getSession();

        $locale = $session->get('_locale', $this->defaultLocale);
        $request->setLocale($locale);
    }
}
