<?php

namespace BikeShare\SmsConnector;

use BikeShare\App\Configuration;

class EuroSmsConnector extends AbstractConnector
{
    /**
     * @var string
     */
    private $gatewayId = '';
    /**
     * @var string
     */
    private $gatewayKey = '';
    /**
     * @var string
     */
    private $gatewaySenderNumber = '';

    public function __construct(
        Configuration $config,
        $debugMode = false
    ) {
        parent::__construct($config, $debugMode);

        if (isset($_GET["sms_text"])) {
            $this->message = $_GET["sms_text"];
        }
        if (isset($_GET["sender"])) {
            $this->number = $_GET["sender"];
        }
        if (isset($_GET["sms_uuid"])) {
            $this->uuid = $_GET["sms_uuid"];
        }
        if (isset($_GET["receive_time"])) {
            $this->time = $_GET["receive_time"];
        }
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $this->ipaddress = $_SERVER['REMOTE_ADDR'];
        }
    }

    public function checkConfig(array $config)
    {
        if ($this->debugMode) {
            return;
        }
        if (empty($config['gatewayId']) || empty($config['gatewayKey']) || empty($config['gatewaySenderNumber'])) {
            throw new \RuntimeException('Invalid EuroSms configuration');
        }
        $this->gatewayId = $config['gatewayId'];
        $this->gatewayKey = $config['gatewayKey'];
        $this->gatewaySenderNumber = $config['gatewaySenderNumber'];
    }

    // confirm SMS received to API
    public function respond()
    {
        if ($this->debugMode) {
            return;
        }
        echo 'ok:', $this->uuid, "\n";
    }

    // send SMS message via API
    public function send($number, $text)
    {
        if ($this->debugMode) {
            return;
        }
        $s = substr(md5($this->gatewayKey . $number), 10, 11);
        $um = urlencode($text);
        $url = sprintf(
            'http://as.eurosms.com/sms/Sender?action=send1SMSHTTP&i=%s&s=%s&d=1&sender=%s&number=%s&msg=%s',
            $this->gatewayId,
            $s,
            $this->gatewaySenderNumber,
            $number,
            $um
        );
        fopen($url, "r");
    }

    public static function getType(): string
    {
        return 'eurosms';
    }
}
