<?php
require("config.php");
require("db.class.php");
$db=new Database($dbServer,$dbUser,$dbPassword,$dbName);
$db->connect();
require("actions-sms.php");
require("connectors/".$connectors["sms"].".php");


$sms=new SMSConnector($connectors["sms"]);

log_sms($sms->UUID(),$sms->Number(),$sms->Time(),$sms->Text(),$sms->IPAddress());

$args = explode(" ", $sms->ProcessedText());

if(!validateNumber($sms->Number()))
   {
   sendSMS($sms->Number(),"Vase cislo nie je registrovane. Your number is not registered.");
   }
else
   {
   switch($args[0])
      {
      case "HELP":
      case "POMOC":
         help($sms->Number());
         break;
      case "FREE":
         freeBikes($sms->Number());
         break;
      case "RENT":
      case "POZICAJ":
         validateReceivedSMS($sms->Number(),count($args),2,"with bike number: RENT 47");
         rent($sms->Number(),$args[1]);//intval
         break;
      case "RETURN":
      case "VRAT":
         validateReceivedSMS($sms->Number(),count($args),3,"with bike number and stand name: RETURN 47 RACKO");
         returnBike($sms->Number(),$args[1],$args[2],trim(urldecode($sms->Text())));
         break;
      case "WHERE":
      case "WHO":
      case "KDE":
      case "KTO":
         validateReceivedSMS($sms->Number(),count($args),2,"with bike number: WHERE 47");
         where($sms->Number(),$args[1]);
         break;
      case "INFO":
         validateReceivedSMS($sms->Number(),count($args),2,"with stand name: INFO RACKO");
         info($sms->Number(),$args[1]);
         break;
      case "NOTE":
         validateReceivedSMS($sms->Number(),count($args),2,"with bike number and problem description: NOTE 47 Flat tire on front wheel");
         note($sms->Number(),$args[1],trim(urldecode($sms->Text())));
         break;
      case "LIST":
      case "ZOZNAM":
         checkUserPrivileges($sms->Number());
         validateReceivedSMS($sms->Number(),count($args),2,"with stand name: LIST RACKO");
         listBikes($sms->Number(),$args[1]);
         break;
      case "ADD":
         checkUserPrivileges($sms->Number());
         validateReceivedSMS($sms->Number(),count($args),3,"with email, phone, fullname: ADD king@earth.com 0901456789 Martin Luther King Jr.");
         add($sms->Number(),$args[1],$args[2],trim(urldecode($sms->Text())));
         break;
      case "REVERT":
         checkUserPrivileges($sms->Number());
         validateReceivedSMS($sms->Number(),count($args),2,"with bike number: REVERT 47");
         revert($sms->Number(),$args[1]);
         break;
      //    case "NEAR":
      //    case "BLIZKO":
      //	near($sms->Number(),$args[1]);
      case "LAST":
         checkUserPrivileges($sms->Number());
         validateReceivedSMS($sms->Number(),count($args),2,"with bike number: LAST 47");
         last($sms->Number(),$args[1]);
         break;
      default:
         unknownCommand($sms->Number(),$args[0]);
      }
   }

$db->conn->commit();
$sms->Respond();

?>