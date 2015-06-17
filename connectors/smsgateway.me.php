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

   function __construct()
      {
      $this->CheckConfig();
      if (isset($_POST["message"])) $this->message=$_POST["message"];
      if (isset($_POST["contact"])) $this->number=$_POST["contact"]["Number"];
      if (isset($_POST["id"])) $this->uuid=$_POST["id"];
      if (isset($_POST["received_at"])) $this->time=date("Y-m-d H:i:s",$_POST["received_at"]);
      $this->ipaddress=$_SERVER['REMOTE_ADDR'];
      }

   function CheckConfig()
      {
      global $gatewayemail,$gatewaypassword,$gatewaysecret;
      if (DEBUG===TRUE) return;
      if (!$gatewayemail OR !$gatewaypassword OR !$gatewaysecret) exit('Please, configure SMS API gateway access in '.__FILE__.'!');
      // when SMS received, check if secret matches or exit, if does not:
      if (isset($_POST["secret"]) AND isset($_POST["id"]) AND $_POST["secret"]<>$gatewaysecret) exit;
      }

   function Text()
      {
      return $this->message;
      }

   function ProcessedText()
      {
      return strtoupper($this->message);
      }

   function Number()
      {
      return $this->number;
      }

   function UUID()
      {
      return $this->uuid;
      }

   function Time()
      {
      return $this->time;
      }

   function IPAddress()
      {
      return $this->ipaddress;
      }

    // confirm SMS received to API
   function Respond()
      {
      if (DEBUG===TRUE) return;
      }

   // send SMS message via API
   function Send($number,$text)
      {
      global $gatewayemail,$gatewaypassword;
      if (DEBUG===TRUE) return;
      $smsgateway=new SmsGateway($gatewayemail,$gatewaypassword);
      $deviceid=1; // use first existing device
      $smsgateway->sendMessageToNumber("+".$number,$text,$deviceid);
      }

   }

?>