<?php

namespace BikeShare\SmsConnector;

/**
 * http://textmagic.com
 * Create callback at: https://textmagic.com
 * URL for callback: http://example.com/receive.php (replace example.com with your website URL)
 */
class TextmagicSmsConnector extends AbstractConnector
{
    /**
     * @var string
     */
    private $gatewayUser = '';
    /**
     * @var string
     */
    private $gatewayPassword = '';
    /**
     * @var string
     */
    private $gatewaySenderNumber = '';

    public function __construct(
        array $config,
        $debugMode = false
    ) {
        $this->debugMode = $debugMode;
        $this->CheckConfig($config);
        if (isset($_POST["text"])) $this->message = $_POST["text"];
        if (isset($_POST["from"])) $this->number = $_POST["from"];
        if (isset($_POST["message_id"])) $this->uuid = $_POST["message_id"];
        if (isset($_POST["timestamp"])) $this->time = date("Y-m-d H:i:s", $_POST["timestamp"]);
        if (isset($_SERVER['REMOTE_ADDR'])) $this->ipaddress = $_SERVER['REMOTE_ADDR'];
    }

    public function CheckConfig(array $config)
    {
        if ($this->debugMode) {
            return;
        }
        if (empty($config['gatewayUser']) || empty($config['gatewayPassword']) || empty($config['gatewaySenderNumber'])) {
            exit('Please, configure SMS API gateway access in ' . __FILE__ . '!');
        }
        $this->gatewayUser = $config['gatewayUser'];
        $this->gatewayPassword = $config['gatewayPassword'];
        $this->gatewaySenderNumber = $config['gatewaySenderNumber'];
    }

    // confirm SMS received to API
    public function Respond()
    {
        if ($this->debugMode) {
            return;
        }
        // do nothing as no response required
    }

    // send SMS message via API
    public function Send($number, $text)
    {
        if ($this->debugMode) {
            return;
        }
        $um = urlencode($text);
        fopen("https://www.textmagic.com/app/api?cmd=send&unicode=0&from=" . $this->gatewaySenderNumber . "&username=" . $this->gatewayUser . "&password=" . $this->gatewayPassword . "&phone=" . $number . "&text=" . $um, "r");
    }

}