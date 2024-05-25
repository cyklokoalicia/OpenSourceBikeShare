<?php

use BikeShare\Authentication\Auth;
use BikeShare\Db\DbInterface;
use BikeShare\Rent\RentSystemFactory;
use BikeShare\Rent\RentSystemInterface;
use BikeShare\SmsConnector\SmsConnectorInterface;
use Psr\Log\LoggerInterface;

require_once 'vendor/autoload.php';
require("config.php");
require("actions-sms.php");

/**
 * @var SmsConnectorInterface $sms
 * @var LoggerInterface $logger
 * @var DbInterface $db
 * @var Auth $auth
 */
$auth = new Auth($db);
log_sms($sms->getUUID(),$sms->getNumber(),$sms->getTime(),$sms->getMessage(),$sms->getIPAddress());

/**
 * @var RentSystemInterface $rentSystem
 */
$rentSystem = RentSystemFactory::create('sms');

$args=preg_split("/\s+/",$sms->getProcessedMessage());//preg_split must be used instead of explode because of multiple spaces

if(!validateNumber($sms->getNumber()))
   {
       $logger->error("Invalid number", ["number" => $sms->getNumber(), 'sms' => $sms]);
   ####
   #$smsSender->send($sms->getNumber(),_('Your number is not registered.'));
   }
else
   {
   switch($args[0])
      {
      case "HELP":
         help($sms->getNumber());
         break;
      case "CREDIT":
          if ($creditSystem->isEnabled() == FALSE) {
              unknownCommand($sms->getNumber(), $args[0]);
              break;
          }
          credit($sms->getNumber());
         break;
      case "FREE":
         freeBikes($sms->getNumber());
         break;
      case "RENT":
         validateReceivedSMS($sms->getNumber(),count($args),2,_('with bike number:')." RENT 47");
         $rentSystem->rentBike($sms->getNumber(), $args[1]);//intval
         break;
      case "RETURN":
         validateReceivedSMS($sms->getNumber(),count($args),3,_('with bike number and stand name:')." RETURN 47 RACKO");
         $rentSystem->returnBike($sms->getNumber(), $args[1], $args[2], trim(urldecode($sms->getMessage())));
         break;
      case "FORCERENT":
         checkUserPrivileges($sms->getNumber());
         validateReceivedSMS($sms->getNumber(),count($args),2,_('with bike number:')." FORCERENT 47");
         $rentSystem->rentBike($sms->getNumber(), $args[1], true);
         break;
      case "FORCERETURN":
         checkUserPrivileges($sms->getNumber());
         validateReceivedSMS($sms->getNumber(),count($args),3,_('with bike number and stand name:')." FORCERETURN 47 RACKO");
         $rentSystem->returnBike($sms->getNumber(), $args[1], $args[2], trim(urldecode($sms->getMessage())), TRUE);
         break;
      case "WHERE":
      case "WHO":
         validateReceivedSMS($sms->getNumber(),count($args),2,_('with bike number:')." WHERE 47");
         where($sms->getNumber(),$args[1]);
         break;
      case "INFO":
         validateReceivedSMS($sms->getNumber(),count($args),2,_('with stand name:')." INFO RACKO");
         info($sms->getNumber(),$args[1]);
         break;
      case "NOTE":
         validateReceivedSMS($sms->getNumber(),count($args),2,_('with bike number/stand name and problem description:')." NOTE 47 "._('Flat tire on front wheel'));
         note($sms->getNumber(),$args[1],trim(urldecode($sms->getMessage())));
         break;
	  case "TAG":
         validateReceivedSMS($sms->getNumber(),count($args),2,_('with stand name and problem description:')." TAG MAINSQUARE "._('vandalism'));
         tag($sms->getNumber(),$args[1],trim(urldecode($sms->getMessage())));
         break;
      case "DELNOTE":
         validateReceivedSMS($sms->getNumber(),count($args),1,_('with bike number and optional pattern. All messages or notes matching pattern will be deleted:')." NOTE 47 wheel");
         delnote($sms->getNumber(),$args[1],trim(urldecode($sms->getMessage())));
         break;
      case "UNTAG":
         validateReceivedSMS($sms->getNumber(),count($args),1,_('with stand name and optional pattern. All notes matching pattern will be deleted for all bikes on that stand:')." UNTAG SAFKO1 pohoda");
         untag($sms->getNumber(),$args[1],trim(urldecode($sms->getMessage())));
         break;
      case "LIST":
         //checkUserPrivileges($sms->Number()); //allowed for all users as agreed
         checkUserPrivileges($sms->getNumber());
         validateReceivedSMS($sms->getNumber(),count($args),2,_('with stand name:')." LIST RACKO");
         validateReceivedSMS($sms->getNumber(),count($args),2,"with stand name: LIST RACKO");
         listBikes($sms->getNumber(),$args[1]);
         break;
      case "ADD":
         checkUserPrivileges($sms->getNumber());
         validateReceivedSMS($sms->getNumber(),count($args),3,_('with email, phone, fullname:')." ADD king@earth.com 0901456789 Martin Luther King Jr.");
         add($sms->getNumber(),$args[1],$args[2],trim(urldecode($sms->getMessage())));
         break;
      case "REVERT":
         checkUserPrivileges($sms->getNumber());
         validateReceivedSMS($sms->getNumber(),count($args),2,_('with bike number:')." REVERT 47");
         revert($sms->getNumber(),$args[1]);
         break;
      //    case "NEAR":
      //    case "BLIZKO":
      //	near($sms->Number(),$args[1]);
      case "LAST":
         checkUserPrivileges($sms->getNumber());
         validateReceivedSMS($sms->getNumber(),count($args),2,_('with bike number:')." LAST 47");
         last($sms->getNumber(),$args[1]);
         break;
      default:
         unknownCommand($sms->getNumber(),$args[0]);
      }
   }

$db->commit();
$sms->respond();
