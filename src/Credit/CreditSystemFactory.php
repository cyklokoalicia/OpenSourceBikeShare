<?php

declare(strict_types=1);

namespace BikeShare\Credit;

use Symfony\Component\DependencyInjection\ServiceLocator;

class CreditSystemFactory
{
    private ServiceLocator $locator;

    public function __construct(
        ServiceLocator $locator
    ) {
        $this->locator = $locator;
    }

    public function getCreditSystem(array $creditConfiguration = []): CreditSystemInterface
    {
        if (!isset($creditConfiguration["enabled"])) {
            $creditConfiguration["enabled"] = false;
        }
        if (!$creditConfiguration["enabled"]) {
            return $this->locator->get(DisabledCreditSystem::class);
        } else {
            return $this->locator->get(CreditSystem::class);
        }
    }
}
