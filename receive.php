<?php
require('functions.php');

$db=new Database($dbServer,$dbUser,$dbPassword,$dbName);
$db->connect();

$message = strtoupper(trim(urldecode($_GET["sms_text"])));
$number = $_GET["sender"]; //$number = intval($_GET["sender"]);
$sms_uuid = $_GET["sms_uuid"];

log_sms($sms_uuid,$number,$_GET["receive_time"],$_GET["sms_text"],$_SERVER['REMOTE_ADDR']);

if (DEBUG!==TRUE) echo 'ok:',$sms_uuid,"\n"; // confirm SMS received to API

$args = preg_split("/\s+/", $message);

if(!validateNumber($number))
   {
   sendSMS($number,"Vase cislo nie je registrovane. Your number is not registered.");
   }
else
   {
   switch($args[0])
      {
      case "HELP":
      case "POMOC":
         help($number);
         break;
      case "FREE":
         freeBikes($number);
         break;
      case "RENT":
      case "POZICAJ":
         validateReceivedSMS($number,count($args),2,"with bike number: RENT 47");
         rent($number,$args[1]);//intval
         break;
      case "RETURN":
      case "VRAT":
         validateReceivedSMS($number,count($args),3,"with bike number and stand name: RETURN 47 RACKO");
         returnBike($number,$args[1],$args[2]);
         break;
      case "WHERE":
      case "KDE":
         validateReceivedSMS($number,count($args),2,"with bike number: WHERE 47");
         where($number,$args[1]);
         break;
      case "INFO":
         validateReceivedSMS($number,count($args),2,"with stand name: INFO RACKO");
         info($number,$args[1]);
         break;
      case "NOTE":
         validateReceivedSMS($number,count($args),2,"with bike number and problem description: NOTE 47 Flat tire on front wheel");
         note($number,$args[1],trim(urldecode($_GET["sms_text"])));
         break;
      case "LIST":
      case "ZOZNAM":
         checkUserPrivileges($number);
         validateReceivedSMS($number,count($args),2,"with stand name: LIST RACKO");
         listBikes($number,$args[1]);
         break;
      case "ADD":
         checkUserPrivileges($number);
         validateReceivedSMS($number,count($args),3,"with email, phone, fullname: ADD king@earth.com 0901456789 Martin Luther King Jr.");
         add($number,$args[1],$args[2],trim(urldecode($_GET["sms_text"])));
         break;
      case "REVERT":
         checkUserPrivileges($number);
         validateReceivedSMS($number,count($args),1,"with bike number: REVERT 47");
         revert($number,$args[1]);
         break;
      //    case "NEAR":
      //    case "BLIZKO":
      //	near($number,$args[1]);
      case "LAST":
         checkUserPrivileges($number);
         validateReceivedSMS($number,count($args),2,"with bike number: LAST 47");
         last($number,$args[1]);
         break;
      default:
         unknownCommand($number,$args[0]);
      }
   }

$db->conn->commit();

?>