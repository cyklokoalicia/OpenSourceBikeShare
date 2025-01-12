<?php

declare(strict_types=1);

namespace BikeShare\Credit;

use Symfony\Component\DependencyInjection\ServiceLocator;

class CreditSystemFactory
{
    private ServiceLocator $locator;
    private bool $isEnabled;

    public function __construct(
        ServiceLocator $locator,
        bool $isEnabled
    ) {
        $this->locator = $locator;
        $this->isEnabled = $isEnabled;
    }

    public function getCreditSystem(): CreditSystemInterface
    {
        if (!$this->isEnabled) {
            return $this->locator->get(DisabledCreditSystem::class);
        } else {
            return $this->locator->get(CreditSystem::class);
        }
    }
}
