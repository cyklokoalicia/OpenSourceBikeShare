<?php

require_once 'vendor/autoload.php';
require("config.php");
require('actions-qrcode.php');

/**
 * @var \Bikeshare\Db\DbInterface
 */
$db=new \Bikeshare\Db\MysqliDb($dbserver,$dbuser,$dbpassword,$dbname);
$db->connect();

if (isset($_COOKIE["loguserid"])) {
    $userid = $db->escape(trim($_COOKIE["loguserid"]));
} else {
    $userid = 0;
}

if (isset($_COOKIE["logsession"])) {
    $session = $db->escape(trim($_COOKIE["logsession"]));
} else {
    $session = '';
}
$request=substr($_SERVER["REQUEST_URI"],strpos($_SERVER["REQUEST_URI"],".php")+5);
$request=explode("/",$request);
$action=$request[0];
if (isset($request[1])) $parameter=$request[1];
else $action=""; // mangled QR code, clear action

switch($action)
   {
   case "rent":
      logrequest($userid,$action);
      checksession();
      $bikeno=$parameter;
      checkbikeno($bikeno);
      rent($userid,$bikeno);
      break;
   case "return":
      logrequest($userid,$action);
      checksession();
      $stand=$parameter;
      checkstandname($stand);
      returnbike($userid,$stand);
      break;
   default:
      unrecognizedqrcode($userid);
   }

?>