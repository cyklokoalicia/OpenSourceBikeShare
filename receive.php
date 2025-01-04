<?php

use BikeShare\Rent\RentSystemInterface;

require_once 'vendor/autoload.php';
require_once "actions-sms.php";

/**
 * @var RentSystemInterface $rentSystem
 */
$rentSystem = $rentSystemFactory->getRentSystem('sms');

$args = preg_split("/\s+/", $sms->getProcessedMessage());//preg_split must be used instead of explode because of multiple spaces

switch ($args[0]) {
    case "RENT":
        validateReceivedSMS($sms->getNumber(), count($args), 2, _('with bike number:') . " RENT 47");
        $rentSystem->rentBike($sms->getNumber(), $args[1]);//intval
        break;
    case "RETURN":
        validateReceivedSMS($sms->getNumber(), count($args), 3, _('with bike number and stand name:') . " RETURN 47 RACKO");
        $rentSystem->returnBike($sms->getNumber(), $args[1], $args[2], trim(urldecode($sms->getMessage())));
        break;
    case "FORCERENT":
        checkUserPrivileges($sms->getNumber());
        validateReceivedSMS($sms->getNumber(), count($args), 2, _('with bike number:') . " FORCERENT 47");
        $rentSystem->rentBike($sms->getNumber(), $args[1], true);
        break;
    case "FORCERETURN":
        checkUserPrivileges($sms->getNumber());
        validateReceivedSMS($sms->getNumber(), count($args), 3, _('with bike number and stand name:') . " FORCERETURN 47 RACKO");
        $rentSystem->returnBike($sms->getNumber(), $args[1], $args[2], trim(urldecode($sms->getMessage())), TRUE);
        break;
    case "TAG":
        validateReceivedSMS($sms->getNumber(), count($args), 2, _('with stand name and problem description:') . " TAG MAINSQUARE " . _('vandalism'));
        tag($sms->getNumber(), $args[1], trim(urldecode($sms->getMessage())));
        break;
    case "DELNOTE":
        validateReceivedSMS($sms->getNumber(), count($args), 1, _('with bike number and optional pattern. All messages or notes matching pattern will be deleted:') . " NOTE 47 wheel");
        delnote($sms->getNumber(), $args[1], trim(urldecode($sms->getMessage())));
        break;
    case "UNTAG":
        validateReceivedSMS($sms->getNumber(), count($args), 1, _('with stand name and optional pattern. All notes matching pattern will be deleted for all bikes on that stand:') . " UNTAG SAFKO1 pohoda");
        untag($sms->getNumber(), $args[1], trim(urldecode($sms->getMessage())));
        break;
    case "LIST":
        //checkUserPrivileges($sms->Number()); //allowed for all users as agreed
        checkUserPrivileges($sms->getNumber());
        validateReceivedSMS($sms->getNumber(), count($args), 2, _('with stand name:') . " LIST RACKO");
        listBikes($sms->getNumber(), $args[1]);
        break;
    case "ADD":
        checkUserPrivileges($sms->getNumber());
        validateReceivedSMS($sms->getNumber(), count($args), 3, _('with email, phone, fullname:') . " ADD king@earth.com 0901456789 Martin Luther King Jr.");
        add($sms->getNumber(), $args[1], $args[2], trim(urldecode($sms->getMessage())));
        break;
    case "REVERT":
        checkUserPrivileges($sms->getNumber());
        validateReceivedSMS($sms->getNumber(), count($args), 2, _('with bike number:') . " REVERT 47");
        revert($sms->getNumber(), $args[1]);
        break;
    default:
        unknownCommand($sms->getNumber(), $args[0]);
}
$db->commit();

