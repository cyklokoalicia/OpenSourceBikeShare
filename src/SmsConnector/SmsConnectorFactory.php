<?php

declare(strict_types=1);

namespace BikeShare\SmsConnector;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

class SmsConnectorFactory
{
    private LoggerInterface $logger;
    private ServiceLocator $locator;
    private string $connectorName;

    public function __construct(
        string $connectorName,
        ServiceLocator $locator,
        LoggerInterface $logger
    ) {
        $this->connectorName = $connectorName;
        $this->logger = $logger;
        $this->locator = $locator;
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
