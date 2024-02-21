<?php

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

    public function __construct(array $config)
    {
        $this->CheckConfig($config);
        if (isset($_GET["sms_text"])) $this->message = $_GET["sms_text"];
        if (isset($_GET["sender"])) $this->number = $_GET["sender"];
        if (isset($_GET["sms_uuid"])) $this->uuid = $_GET["sms_uuid"];
        if (isset($_GET["receive_time"])) $this->time = $_GET["receive_time"];
        if (isset($_SERVER['REMOTE_ADDR'])) $this->ipaddress = $_SERVER['REMOTE_ADDR'];
    }

    public function CheckConfig(array $config)
    {
        if (DEBUG === TRUE) {
            return;
        }
        if (empty($config['gatewayId']) or empty($config['gatewayKey']) or empty($config['gatewaySenderNumber'])) {
            exit('Please, configure SMS API gateway access in config.php!');
        }
        $this->gatewayId = $config['gatewayId'];
        $this->gatewayKey = $config['gatewayKey'];
        $this->gatewaySenderNumber = $config['gatewaySenderNumber'];
    }

    // confirm SMS received to API
    public function Respond()
    {
        if (DEBUG === TRUE) {
            return;
        }
        echo 'ok:', $this->uuid, "\n";
    }

    // send SMS message via API
    public function Send($number, $text)
    {
        if (DEBUG === TRUE) {
            return;
        }
        $s = substr(md5($this->gatewayKey . $number), 10, 11);
        $um = urlencode($text);
        fopen("http://as.eurosms.com/sms/Sender?action=send1SMSHTTP&i=$this->gatewayId&s=$s&d=1&sender=$this->gatewaySenderNumber&number=$number&msg=$um", "r");
    }
}