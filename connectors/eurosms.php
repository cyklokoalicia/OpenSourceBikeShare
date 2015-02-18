<?php

$gatewayId="";
$gatewayKey="";
$gatewaySenderNumber="";

class SMSConnector
   {

   function __construct()
      {
      $this->CheckConfig();
      if (isset($_GET["sms_text"])) $this->message=$_GET["sms_text"];
      if (isset($_GET["sender"])) $this->number=$_GET["sender"];
      if (isset($_GET["sms_uuid"])) $this->uuid=$_GET["sms_uuid"];
      if (isset($_GET["receive_time"])) $this->time=$_GET["receive_time"];
      $this->ipaddress=$_SERVER['REMOTE_ADDR'];
      }

   function CheckConfig()
      {
      global $gatewayId,$gatewayKey,$gatewaySenderNumber;
      if (DEBUG===TRUE) return;
      if (!$gatewayId OR !$gatewayKey OR !$gatewaySenderNumber) exit('Please, configure SMS API gateway access in '.__FILE__.'!');
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
      echo 'ok:',$this->uuid,"\n";
      }

   // send SMS message via API
   function Send($number,$text)
      {
      global $gatewayId,$gatewayKey,$gatewaySenderNumber;
      if (DEBUG===TRUE) return;
      $s=substr(md5($gatewayKey.$number),10,11);
      $um=urlencode($text);
      fopen("http://as.eurosms.com/sms/Sender?action=send1SMSHTTP&i=$gatewayId&s=$s&d=1&sender=$gatewaySenderNumber&number=$number&msg=$um","r");
      }

   }

?>