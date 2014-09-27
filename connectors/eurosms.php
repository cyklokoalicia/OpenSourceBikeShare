<?php

$this->message=$_GET["sms_text"];
$this->number=$_GET["sender"];
$this->uuid=$_GET["sms_uuid"];
$this->time=$_GET["receive_time"];
$this->ipaddress=$_SERVER['REMOTE_ADDR'];

?>