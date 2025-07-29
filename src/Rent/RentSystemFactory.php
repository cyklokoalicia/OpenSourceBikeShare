<?php

declare(strict_types=1);

namespace BikeShare\Rent;

use Symfony\Component\DependencyInjection\ServiceLocator;

class RentSystemFactory
{
    public function __construct(
        private readonly ServiceLocator $locator
    ) {
    }

    public function getRentSystem(string $type): RentSystemInterface
    {
        if ($this->locator->has($type)) {
            return $this->locator->get($type);
        }

        throw new \InvalidArgumentException('Invalid rent system type');
    }
}
