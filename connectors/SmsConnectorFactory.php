<?php

include_once("SmsConnectorInterface.php");
include_once("DisabledConnector.php");
include_once("EuroSmsConnector.php");
include_once("LoopbackConnector.php");
include_once("SmsGatewayConnector.php");
include_once("TextmagicSmsConnector.php");

class SmsConnectorFactory
{
    /**
     * @param string $connector
     * @param array $config
     * @return SmsConnectorInterface
     */
    public function getConnector($connector, array $config)
    {
        switch ($connector) {
            case 'loopback':
                return new LoopbackConnector($config);
            case 'eurosms':
                return new EuroSmsConnector($config);
            case 'smsgateway.me':
                return new SmsGatewayConnector($config);
            case 'textmagic.com':
                return new TextmagicSmsConnector($config);
            default:
                return new DisabledConnector();
        }
    }
}