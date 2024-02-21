<?php

/** https://smsgateway.me
 * Create callback at: https://smsgateway.me/admin/callbacks/index
 * Event: Received
 * Method: HTTP
 * Action: http://example.com/receive.php (replace example.com with your website URL)
 * Secret: secretstring (e.g. your password)
 ***/

require('smsGateway/SmsGateway.php');

class SmsGatewayConnector extends AbstractConnector
{
    /**
     * @var string
     */
    private $gatewayEmail = '';
    /**
     * @var string
     */
    private $gatewayPassword = '';
    /**
     * @var string
     */
    private $gatewaySecret = '';

    public function __construct(array $config)
    {
        $this->CheckConfig($config);
        if (isset($_POST["message"])) $this->message = $_POST["message"];
        if (isset($_POST["contact"])) $this->number = $_POST["contact"]["Number"];
        if (isset($_POST["id"])) $this->uuid = $_POST["id"];
        if (isset($_POST["received_at"])) $this->time = date("Y-m-d H:i:s", $_POST["received_at"]);
        if (isset($_SERVER['REMOTE_ADDR'])) $this->ipaddress = $_SERVER['REMOTE_ADDR'];
        // when SMS received, check if secret matches or exit, if does not:
        if (isset($_POST["secret"]) and isset($_POST["id"]) and $_POST["secret"] <> $this->gatewaySecret) {
            exit;
        }
    }

    public function CheckConfig(array $config)
    {
        if (DEBUG === TRUE) {
            return;
        }
        if (empty($config['gatewayEmail']) || empty($config['gatewayPassword']) || empty($config['gatewaySecret'])) {
            exit('Please, configure SMS API gateway access in config.php!');
        }
        $this->gatewayEmail = $config['gatewayEmail'];
        $this->gatewayPassword = $config['gatewayPassword'];
        $this->gatewaySecret = $config['gatewaySecret'];
    }

    public function ProcessedText()
    {
        return strtoupper($this->message);
    }


    // confirm SMS received to API
    public function Respond()
    {
        if (DEBUG === TRUE) {
            return;
        }
        // do nothing as no response required
    }

    // send SMS message via API
    public function Send($number, $text)
    {
        if (DEBUG === TRUE) {
            return;
        }
        $smsgateway = new SmsGateway($this->gatewayEmail, $this->gatewayPassword);
        $deviceid = 1; // use first existing device
        $smsgateway->sendMessageToNumber("+" . $number, $text, $deviceid);
    }
}