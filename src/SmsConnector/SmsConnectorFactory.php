<?php

namespace BikeShare\SmsConnector;

use Psr\Log\LoggerInterface;

class SmsConnectorFactory
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }

    /**
     * @param string $connector
     * @param array $config
     * @return SmsConnectorInterface
     */
    public function getConnector($connector, array $config, $debugMode = false)
    {
        try {
            switch ($connector) {
                case 'loopback':
                    return new LoopbackConnector($config, $debugMode);
                case 'eurosms':
                    return new EuroSmsConnector($config, $debugMode);
                case 'smsgateway.me':
                    return new SmsGatewayConnector($config, $debugMode);
                case 'textmagic.com':
                    return new TextmagicSmsConnector($config, $debugMode);
                default:
                    return new DisabledConnector();
            }
        } catch (\Exception $exception) {
            $this->logger->error('Error creating SMS connector', compact('connector', 'exception'));

            return new DisabledConnector();
        }
    }
}
