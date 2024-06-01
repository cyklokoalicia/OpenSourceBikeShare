<?php

declare(strict_types=1);

namespace BikeShare\SmsConnector;

use BikeShare\App\Configuration;
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
        Configuration $configuration,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->configuration = $configuration;
        $this->locator = $locator;
        if (empty($connectorConfig['sms'])) {
            $connectorConfig['sms'] = 'disabled';
        }
        $this->connectorConfig = $connectorConfig;
    }

    public function getConnector(): SmsConnectorInterface
    {
        try {
            return $this->locator->get($this->connectorConfig['sms']);
        } catch (\Throwable $exception) {
            $connector = $this->connectorConfig['sms'];
            $this->logger->error('Error creating SMS connector', compact('connector', 'exception'));

            return new DisabledConnector($this->configuration, true);
        }
    }
}
