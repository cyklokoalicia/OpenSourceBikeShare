<?php

use BikeShare\Rent\RentSystemInterface;
use BikeShare\SmsConnector\SmsConnectorInterface;

require_once 'vendor/autoload.php';
require_once "actions-sms.php";

/**
 * @var RentSystemInterface $rentSystem
 * @var SmsConnectorInterface $sms
 */
$rentSystem = $rentSystemFactory->getRentSystem('sms');

$smsMessage = strtoupper($sms->getProcessedMessage());

$args = preg_split("/\s+/", $smsMessage);//preg_split must be used instead of explode because of multiple spaces

switch ($args[0]) {
    case "RENT":
        validateReceivedSMS($sms->getNumber(), count($args), 2, _('with bike number:') . " RENT 47");
        $rentSystem->rentBike($sms->getNumber(), $args[1]);//intval
        break;
    case "RETURN":
        validateReceivedSMS($sms->getNumber(), count($args), 3, _('with bike number and stand name:') . " RETURN 47 RACKO");
        $rentSystem->returnBike($sms->getNumber(), $args[1], $args[2], $sms->getProcessedMessage());
        break;
    case "FORCERENT":
        checkUserPrivileges($sms->getNumber());
        validateReceivedSMS($sms->getNumber(), count($args), 2, _('with bike number:') . " FORCERENT 47");
        $rentSystem->rentBike($sms->getNumber(), $args[1], true);
        break;
    case "FORCERETURN":
        checkUserPrivileges($sms->getNumber());
        validateReceivedSMS($sms->getNumber(), count($args), 3, _('with bike number and stand name:') . " FORCERETURN 47 RACKO");
        $rentSystem->returnBike($sms->getNumber(), $args[1], $args[2], $sms->getProcessedMessage(), true);
        break;
    case "REVERT":
        checkUserPrivileges($sms->getNumber());
        validateReceivedSMS($sms->getNumber(), count($args), 2, _('with bike number:') . " REVERT 47");
        $rentSystem->revertBike($sms->getNumber(), $args[1]);
        break;
    default:
        unknownCommand($sms->getNumber(), $args[0]);
}

