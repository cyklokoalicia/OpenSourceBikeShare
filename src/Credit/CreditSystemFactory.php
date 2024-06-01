<?php

declare(strict_types=1);

namespace BikeShare\Credit;

use Symfony\Component\DependencyInjection\ServiceLocator;

class CreditSystemFactory
{
    private ServiceLocator $locator;
    private array $creditConfiguration;

    public function __construct(
        ServiceLocator $locator,
        array $creditConfiguration
    ) {
        $this->locator = $locator;
        $this->creditConfiguration = $creditConfiguration;
    }

    public function getCreditSystem(): CreditSystemInterface
    {
        if (!isset($this->creditConfiguration["enabled"])) {
            $this->creditConfiguration["enabled"] = false;
        }
        if (!$this->creditConfiguration["enabled"]) {
            return $this->locator->get(DisabledCreditSystem::class);
        } else {
            return $this->locator->get(CreditSystem::class);
        }
    }
}
