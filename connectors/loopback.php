<?php

class SMSConnector
   {

   function __construct($connector)
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
      if (DEBUG===TRUE) return;
      define('CURRENTDIR',dirname($_SERVER['SCRIPT_FILENAME']));
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
      $log="<|~".$_GET["sender"]."|~".$this->message."\n";
      foreach ($this->store as $message)
         {
         $log.=$message;
         }
      file_put_contents("connectors/loopback/loopback.log",$log,FILE_APPEND);
      }

   // send SMS message via API
   function Send($number,$text)
      {
      $this->store[]=">|~".$number."|~".urlencode($text)."\n";
      }

   // if Respond is not called, this forces the log to save / flush
   function __destruct()
      {
      $log="";
      if (isset($this->store) AND is_array($this->store))
         {
         foreach ($this->store as $message)
            {
            $log.=$message;
            }
         file_put_contents(CURRENTDIR."/connectors/loopback/loopback.log",$log,FILE_APPEND);
         }
      }

   }

?>