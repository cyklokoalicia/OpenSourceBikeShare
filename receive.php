<?php
require("config.php");
require("db.class.php");
$db=new Database($dbServer,$dbUser,$dbPassword,$dbName);
$db->connect();
require("actions-sms.php");

log_sms($sms->UUID(),$sms->Number(),$sms->Time(),$sms->Text(),$sms->IPAddress());

$args=preg_split("/\s+/",$sms->ProcessedText());//preg_split must be used instead of explode because of multiple spaces

if(!validateNumber($sms->Number()))
   {
   sendSMS($sms->Number(),"Vase cislo nie je registrovane. Your number is not registered.");
   }
else
   {
   switch($args[0])
      {
      case "HELP":
         help($sms->Number());
         break;
      case "CREDIT":
         if (iscreditenabled()==FALSE)
            {
            unknownCommand($sms->Number(),$args[0]);
            break;
            }
         credit($sms->Number());
         break;
      case "FREE":
         freeBikes($sms->Number());
         break;
      case "RENT":
         validateReceivedSMS($sms->Number(),count($args),2,"with bike number: RENT 47");
         rent($sms->Number(),$args[1]);//intval
         break;
      case "RETURN":
         validateReceivedSMS($sms->Number(),count($args),3,"with bike number and stand name: RETURN 47 RACKO");
         returnBike($sms->Number(),$args[1],$args[2],trim(urldecode($sms->Text())));
         break;
      case "FORCERENT":
         checkUserPrivileges($sms->Number());
         validateReceivedSMS($sms->Number(),count($args),2,"with bike number: FORCERENT 47");
         rent($sms->Number(),$args[1],TRUE);
         break;
      case "FORCERETURN":
         checkUserPrivileges($sms->Number());
         validateReceivedSMS($sms->Number(),count($args),3,"with bike number and stand name: FORCERETURN 47 RACKO");
         returnBike($sms->Number(),$args[1],$args[2],trim(urldecode($sms->Text())),TRUE);
         break;
      case "WHERE":
      case "WHO":
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