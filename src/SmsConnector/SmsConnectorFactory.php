<?php

namespace BikeShare\SmsConnector;

class SmsConnectorFactory
{
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
        } catch (\Exception $e) {
            // TODO add logging instead of triggering error
            trigger_error($e->getMessage(), E_USER_WARNING);
            return new DisabledConnector();
        }
    }
}
