<?php
/*** http://textmagic.com
Create callback at: https://textmagic.com
URL for callback: http://example.com/receive.php (replace example.com with your website URL)
***/

$gatewayuser="";
$gatewaypassword="";
$gatewaysendernumber="";

class SMSConnector
   {

   function __construct()
      {
      $this->CheckConfig();
      if (isset($_POST["text"])) $this->message=$_POST["text"];
      if (isset($_POST["from"])) $this->number=$_POST["from"];
      if (isset($_POST["message_id"])) $this->uuid=$_POST["message_id"];
      if (isset($_POST["timestamp"])) $this->time=date("Y-m-d H:i:s",$_POST["timestamp"]);
      $this->ipaddress=$_SERVER['REMOTE_ADDR'];
      }

   function CheckConfig()
      {
      global $gatewayuser,$gatewaypassword,$gatewaysendernumber;
      if (DEBUG===TRUE) return;
      if (!$gatewayuser OR !$gatewaypassword OR !$gatewaysendernumber) exit('Please, configure SMS API gateway access in '.__FILE__.'!');
      }

   function Text()
      {
      return $this->message;
      }

   function ProcessedText()
      {
      return strtoupper(trim(urldecode($this->message)));
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
      // do nothing as no response required
      }

   // send SMS message via API
   function Send($number,$text)
      {
      global $gatewayuser,$gatewaypassword,$gatewaysendernumber;
      if (DEBUG===TRUE) return;
      $um=urlencode($text);
      fopen("https://www.textmagic.com/app/api?cmd=send&unicode=0&from=".$gatewaysendernumber."&username=".$gatewayuser."&password=".$gatewaypassword."&phone=".$number."&text=".$um,"r");
      }

   }

?>