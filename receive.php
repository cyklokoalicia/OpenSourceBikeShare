<?php
require("config.php");
require("external/rb.php");
R::setup('mysql:host='.$dbserver.';dbname='.$dbname, $dbuser, $dbpassword);
R::freeze(true);
R::debug(true, 2);
R::addDatabase('localdb', 'mysql:host='.$dbserver.';dbname='.$dbname, $dbuser, $dbpassword, true);
R::begin();
require("actions-sms.php");

log_sms($sms->UUID(), $sms->Number(), $sms->Time(), $sms->Text(), $sms->IPAddress());

$args=preg_split("/\s+/", $sms->ProcessedText());//preg_split must be used instead of explode because of multiple spaces

/**
 * TODO validation of bike / stand for commands
**/

if (!validateNumber($sms->Number())) {
    sendSMS($sms->Number(), _('Your number is not registered.'));
} else {
    switch ($args[0]) {
        case "HELP":
            help($sms->Number());
            break;
        case "CREDIT":
            if (iscreditenabled()==false) {
                unknownCommand($sms->Number(), $args[0]);
                break;
            }
            credit($sms->Number());
            break;
        case "FREE":
            freeBikes($sms->Number());
            break;
        case "RENT":
            validateReceivedSMS($sms->Number(), count($args), 2, _('with bike number:')." RENT 47");
            rentbike($sms->Number(), $args[1]);//intval
            break;
        case "RETURN":
            validateReceivedSMS($sms->Number(), count($args), 3, _('with bike number and stand name:')." RETURN 47 RACKO");
           /*
           if (!preg_match("/return[\s,\.]+[0-9]+[\s,\.]+[a-zA-Z0-9]+[\s,\.]+(.*)/i",$message ,$matches))
            {
            $userNote="";
            }
           else $userNote=trim($matches[1]);
           pass note only or empty string if no note sent
           */
            returnbike($sms->Number(), $args[1], $args[2], trim(urldecode($sms->Text())));
            break;
        case "FORCERENT":
            checkUserPrivileges($sms->Number());
            validateReceivedSMS($sms->Number(), count($args), 2, _('with bike number:')." FORCERENT 47");
            rentbike($sms->Number(), $args[1], true);
            break;
        case "FORCERETURN":
            checkUserPrivileges($sms->Number());
            validateReceivedSMS($sms->Number(), count($args), 3, _('with bike number and stand name:')." FORCERETURN 47 RACKO");
           /*
           if (!preg_match("/return[\s,\.]+[0-9]+[\s,\.]+[a-zA-Z0-9]+[\s,\.]+(.*)/i",$message ,$matches))
            {
            $userNote="";
            }
           else $userNote=trim($matches[1]);
           pass note only or empty string if no note sent
           */
            returnbike($sms->Number(), $args[1], $args[2], trim(urldecode($sms->Text())), true);
            break;
        case "WHERE":
        case "WHO":
            validateReceivedSMS($sms->Number(), count($args), 2, _('with bike number:')." WHERE 47");
            where($sms->Number(), $args[1]);
            break;
        case "INFO":
            validateReceivedSMS($sms->Number(), count($args), 2, _('with stand name:')." INFO RACKO");
            info($sms->Number(), $args[1]);
            break;
        case "NOTE":
            validateReceivedSMS($sms->Number(), count($args), 2, _('with bike number/stand name and problem description:')." NOTE 47 "._('Flat tire on front wheel'));
            note($sms->Number(), $args[1], trim(urldecode($sms->Text())));
            break;
        case "TAG":
            validateReceivedSMS($sms->Number(), count($args), 2, _('with stand name and problem description:')." TAG MAINSQUARE "._('vandalism'));
            tag($sms->Number(), $args[1], trim(urldecode($sms->Text())));
            break;
        case "DELNOTE":
            validateReceivedSMS($sms->Number(), count($args), 1, _('with bike number and optional pattern. All messages or notes matching pattern will be deleted:')." NOTE 47 wheel");
            delnote($sms->Number(), $args[1], trim(urldecode($sms->Text())));
            break;
        case "UNTAG":
            validateReceivedSMS($sms->Number(), count($args), 1, _('with stand name and optional pattern. All notes matching pattern will be deleted for all bikes on that stand:')." UNTAG SAFKO1 pohoda");
            untag($sms->Number(), $args[1], trim(urldecode($sms->Text())));
            break;
        case "LIST":
           //checkUserPrivileges($sms->Number()); //allowed for all users as agreed
            checkUserPrivileges($sms->Number());
            validateReceivedSMS($sms->Number(), count($args), 2, _('with stand name:')." LIST RACKO");
            listBikes($sms->Number(), $args[1]);
            break;
        case "ADD":
            checkUserPrivileges($sms->Number());
            validateReceivedSMS($sms->Number(), count($args), 3, _('with email, phone, fullname:')." ADD king@earth.com 0901456789 Martin Luther King Jr.");
            add($sms->Number(), $args[1], $args[2], trim(urldecode($sms->Text())));
            break;
        case "REVERT":
            checkUserPrivileges($sms->Number());
            validateReceivedSMS($sms->Number(), count($args), 2, _('with bike number:')." REVERT 47");
            revert($sms->Number(), $args[1]);
            break;
       //    case "NEAR":
       //    case "BLIZKO":
       //	near($sms->Number(),$args[1]);
        case "LAST":
            checkUserPrivileges($sms->Number());
            validateReceivedSMS($sms->Number(), count($args), 2, _('with bike number:')." LAST 47");
            last($sms->Number(), $args[1]);
            break;
        default:
            unknownCommand($sms->Number(), $args[0]);
    }
}

R::commit();
$sms->Respond();
R::close();
