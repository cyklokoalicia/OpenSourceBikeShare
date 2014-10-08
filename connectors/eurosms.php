<?php

class SMSConnector
   {

   function __construct($connector)
      {
      $this->message=$_GET["sms_text"];
      $this->number=$_GET["sender"];
      $this->uuid=$_GET["sms_uuid"];
      $this->time=$_GET["receive_time"];
      $this->ipaddress=$_SERVER['REMOTE_ADDR'];
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

   function Send($number,$text)
      {
      global $gatewayId,$gatewayKey,$gatewaySenderNumber;
      $s=substr(md5($gatewayKey.$number),10,11);
      $um=urlencode($text);
      fopen("http://as.eurosms.com/sms/Sender?action=send1SMSHTTP&i=$gatewayId&s=$s&d=1&sender=$gatewaySenderNumber&number=$number&msg=$um","r");
      }

   }

?>