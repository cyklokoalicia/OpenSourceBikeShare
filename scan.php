<?php

use BikeShare\Authentication\Auth;
use BikeShare\Db\DbInterface;
use BikeShare\Db\MysqliDb;
use BikeShare\User\User;

require_once 'vendor/autoload.php';
require("config.php");
require('actions-qrcode.php');

/**
 * @var DbInterface $db
 */
$db=new MysqliDb($dbserver,$dbuser,$dbpassword,$dbname);
$db->connect();
$user = new User($db);
$auth = new Auth($db);

$auth->refreshSession();
$userid = $auth->getUserId();
$session = $auth->getSessionId();

$request=substr($_SERVER["REQUEST_URI"],strpos($_SERVER["REQUEST_URI"],".php")+5);
$request=explode("/",$request);
$action=$request[0];
if (isset($request[1])) $parameter=$request[1];
else $action=""; // mangled QR code, clear action

switch($action)
   {
   case "rent":
      logrequest($userid,$action);
      $bikeno=$parameter;
      checkbikeno($bikeno);
      rent($userid,$bikeno);
      break;
   case "return":
      logrequest($userid,$action);
      $stand=$parameter;
      checkstandname($stand);
      returnbike($userid,$stand);
      break;
   default:
      unrecognizedqrcode($userid);
   }
