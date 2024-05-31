<?php

declare(strict_types=1);

namespace BikeShare\SmsConnector;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

class SmsConnectorFactory
{
    private LoggerInterface $logger;
    private ServiceLocator $locator;
    private array $connectorConfig;

    public function __construct(
        array $connectorConfig,
        ServiceLocator $locator,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->locator = $locator;
        $this->connectorConfig = $connectorConfig;
    }

    public function getConnector(): SmsConnectorInterface
    {
        try {
            return $this->locator->get($this->connectorConfig['sms']);
        } catch (\Throwable $exception) {
            $connector = $this->connectorConfig['sms'] ?? 'unknown';
            $this->logger->error('Error creating SMS connector', compact('connector', 'exception'));

            return new DisabledConnector();
        }
    }
}
