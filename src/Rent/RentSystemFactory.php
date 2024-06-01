<?php

namespace BikeShare\Rent;

use Symfony\Component\DependencyInjection\ServiceLocator;

class RentSystemFactory
{
    private ServiceLocator $locator;

    public function __construct(
        ServiceLocator $locator
    ) {
        $this->locator = $locator;
    }

    public function getRentSystem(string $type): RentSystemInterface
    {
        if ($this->locator->has($type)) {
            return $this->locator->get($type);
        }

        throw new \InvalidArgumentException('Invalid rent system type');
    }
}
