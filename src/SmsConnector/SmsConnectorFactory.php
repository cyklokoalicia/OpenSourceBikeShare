<?php

declare(strict_types=1);

namespace BikeShare\SmsConnector;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

class SmsConnectorFactory
{
    public function __construct(
        private string $connectorName,
        private ServiceLocator $locator,
        private LoggerInterface $logger,
    ) {
    }

    public function getConnector(): SmsConnectorInterface
    {
        try {
            return $this->locator->get($this->connectorName);
        } catch (\Throwable $exception) {
            $connector = $this->connectorName;
            $this->logger->error('Error creating SMS connector', compact('connector', 'exception'));

            return new DisabledConnector([], true);
        }
    }
}
