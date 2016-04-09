<?php
/*** https://smsgateway.me
Create callback at: https://smsgateway.me/admin/callbacks/index
Event: Received
Method: HTTP
Action: http://example.com/receive.php (replace example.com with your website URL)
Secret: secretstring (e.g. your password)
***/

$gatewayemail="";
$gatewaypassword="";
$gatewaysecret=""; // your "Secret" from callback

require('smsGateway.me.class.php');

class SMSConnector
{

    private $_email = "";
    private $_password = "";
    private $_secret = "";
    public $deviceId;

    /**
     * Sets whether the system should ask for deviceId or use 1 instead
     * @var boolean
     */
    public $findDevice = true;

    public function __construct()
    {
        $this->CheckConfig();
        if (isset($_POST["message"])) {
            $this->message = $_POST["message"];
        }

        if (isset($_POST["contact"])) {
            $this->number = $_POST["contact"]["number"];
        }

        if (isset($_POST["id"])) {
            $this->uuid = $_POST["id"];
        }

        if (isset($_POST["received_at"])) {
            $this->time = date("Y-m-d H:i:s", $_POST["received_at"]);
        }

        $this->ipaddress = $_SERVER['REMOTE_ADDR'];


        
    }

    /**
     * Returns first deviceId or 1
     * @return integer deviceId
     */
    public function getDeviceId()
    {
        if (!$this->findDevice) {
            return 1;
        }

        $smsgateway = new SmsGateway($this->_email, $this->_password);
        $result = $smsgateway->getDevices();


        if ($result['response']['success'] && isset($result['response']['result']['data'][0]['id'])) {
            return $result['response']['result']['data'][0]['id'];
        } else {
            return 1;
        }

    }

    public function CheckConfig()
    {
        global $gatewayemail, $gatewaypassword, $gatewaysecret;
        if (DEBUG === true) {
            return;
        }

        if (!$gatewayemail or !$gatewaypassword or !$gatewaysecret) {
            exit('Please, configure SMS API gateway access in ' . __FILE__ . '!');
        }

        // when SMS received, check if secret matches or exit, if does not:
        if (isset($_POST["secret"]) and isset($_POST["id"]) and $_POST["secret"] != $gatewaysecret) {
            exit;
        }
        $this->_email = $gatewayemail;
        $this->_password = $gatewaypassword;
        $this->secret = $gatewaysecret;

        $this->deviceId = $this->getDeviceId();
    }

    public function Text()
    {
        return $this->message;
    }

    public function ProcessedText()
    {
        return strtoupper($this->message);
    }

    public function Number()
    {
        return substr($this->number, 1); //remove + because smsgatewayme uses numbers with + but system doesn't
    }

    public function UUID()
    {
        return $this->uuid; //UUID zn. unikatne id, kedze rozne brany maju rozne id tak je to drist a moze nastat konflikt
    }

    public function Time()
    {
        return $this->time;
    }

    public function IPAddress()
    {
        return $this->ipaddress;
    }

    // confirm SMS received to API
    public function Respond()
    {
        if (DEBUG === true) {
            return;
        }

    }

    // send SMS message via API
    public function Send($number, $text)
    {

        if (DEBUG === true) {
            return;
        }

        $smsgateway = new SmsGateway($this->_email, $this->_password);

        $result = $smsgateway->sendMessageToNumber("+" . $number, $text, $this->deviceId);
        return $result['success'];
    }
}
