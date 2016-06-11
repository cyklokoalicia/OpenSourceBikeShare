<?php
require("common.php");

function status($action, $result, $values = false)
{
    if ($action == 'CREDIT') {
        if ($result == OK) {
            sendSMS($number, _('Your remaining credit:')." ".$values->usercredit.getcreditcurrency());
        }
    } elseif ($action == 'LISTBIKES') {
        if ($result == OK) {
            $listbikes = "";
            foreach ($values->bicycles as $bicycle) {
                $listbikes .= $bicycle;
                if ($values->stacktopbike == $bicycle) {
                    $listbikes .= " "._('(first)');
                }
                $listbikes .= ",";
            }
            if ($values->standcount>1) {
                $listbikes = substr($listbikes, 0, strlen($listbikes)-1);
            }
            sendSMS($number, sprintf(ngettext('%d bike', '%d bikes', $values->standcount), $values->standcount)." "._('on stand')." ".$values->standname.": ".$listbikes);
        } elseif ($result == 100) {
            sendSMS($number, _('Stand')." ".$values->standname." "._('is empty').".");
        }
    } elseif ($action == 'RENT') {
        if ($result == OK) {
            $message = _('Bike')." ".$values->bikenum.": "._('Open with code')." ".$values->currentcode.". "._('Change code immediately to')." ".$values->newcode." "._('(open,rotate metal part,set new code,rotate metal part back)').".";
            if ($values->note) {
                $message .= "("._('Reported issue:').":".$values->note.")";
            }
            sendSMS($number, $message);
            if (isset($values->currentusernumber)) {
                sendSMS($values->currentusernumber, _('System override').": "._('Your rented bike')." ".$bikeNum." "._('has been rented by admin').".");
            }
        } elseif ($result == 100) {
            sendSMS($number, _('You can not rent any bikes. Contact the admins to lift the ban.'));
        } elseif ($result == 101) {
            sendSMS($number, _('You can only rent')." ".sprintf(ngettext('%d bike', '%d bikes', $values->userlimit), $values->userlimit)." "._('at once').".");
        } elseif ($result == 102) {
            sendSMS($number, _('You can only rent')." ".sprintf(ngettext('%d bike', '%d bikes', $values->userlimit), $values->userlimit)." "._('at once')." "._('and you have already rented')." ".$values->userlimit.".");
        } elseif ($result == 110) {
            sendSMS($number, _('Bike')." ".$values->bikenum." "._('is not rentable now, you have to rent bike')." ".$values->stacktopbike." "._('from this stand').".");
        } elseif ($result == 120) {
            sendSMS($number, _('You have already rented the bike')." ".$values->bikenum.". "._('Code is')." ".$values->currentcode.". "._('Return bike with command:')." RETURN "._('bikenumber')." "._('standname').".");
        } elseif ($result == 121) {
            sendSMS($number, _('Bike')." ".$values->bikenum." "._('is already rented by someone else').".");
        } elseif ($result == 130) {
            sendSMS($number, _('Please, recharge your credit:')." ".$values->credit.getcreditcurrency().". "._('Credit required:')." ".$values->requiredcredit.getcreditcurrency().".");
        }
    } elseif ($action == 'RETURN') {
        if ($result == OK) {
            $message = _('Bike')." ".$values->bikenum." "._('returned to stand')." ".$values->standname.". "._('Make sure you set code to')." ".$currentCode.".";
            $message .= " "._('Rotate lockpad to 0000.');
            if (iscreditenabled()) {
                $message .= " "._('Credit').": ".getusercredit($values->userid).getcreditcurrency();
                if (isset($values->creditchange)) {
                    $message .= " (-".$values->creditchange.")";
                }
                $message .= ".";
            }
            if (isset($values->note)) {
                $message .= " ("._('note').":".$tempnote.")";
            }
            sendSMS($number, $message);
            if (isset($values->currentusernumber)) {
                sendSMS($values->currentusernumber, _('System override').": "._('Your rented bike')." ".$values->bikenum." "._('has been returned by admin').".");
            }
        } elseif ($result == 100) {
            sendSMS($number, _('You have no rented bikes currently.'));
        } elseif ($result == 102) {
            $message = _('You do not have the bike')." ".$values->bikenum." rented.";
            if (isset($values->bikelist)) {
                $message .= " "._('You have rented the following')." ".sprintf(ngettext('%d bike', '%d bikes', $values->countrented), $values->countrented).": ".$values->bikelist.".";
            }
            sendSMS($number, $message);
        } elseif ($result == 103) {
            sendSMS($number, _('Bike')." ".$values->bikenum." "._('is not rented. Saint Thomas, the patron of unrented bikes, prohibited returning it.'));
        }
    } elseif ($action == 'CHECKBIKE') {
        if ($result == 100) {
            response('<h3>Bike '.$bikenum.' does not exist!</h3>', ERROR);
        }
    } elseif ($action == 'CHECKSTAND') {
        if ($result == 100) {
            sendSMS($values->number, _("Stand")." ".$values->standname._("does not exist")."."._("Stands are marked by CAPITALLETTERS").".");
        }
    } elseif ($action == 'WHERE') {
        if ($result == 100) {
            $message = _('Bike')." ".$values->bikenum." "._('is at stand')." ".$values->standname.".";
            if (isset($values->note)) {
                $message .= " ("._('Reported issue:').":".$values->note.")";
            }
            sendSMS($number, $message);
        } elseif ($result == 101) {
            $message = _('Bike')." ".$values->bikenum." "._('is rented by')." ".$values->username." (+".$values->phone.").";
            if (isset($values->note)) {
                $message .= " ("._('Reported issue:').":".$values->note.")";
            }
            sendSMS($number, $message);
        }
    } elseif ($action == 'DELNOTE') {
        if ($result == OK) {
            $message = _('Note for bike').' '.$values->bikenum.' '._('deleted').'.';
            sendSMS($number, $message);
        } elseif ($result == 100) {
        }
    }

    response('Unhandled status '.$result.' in '.$action.' in file '.__FILE__.'.', ERROR);
}

function help($number)
{
    $userid = getUser($number);
    $privileges = getprivileges($userid);
    if ($privileges > 0) {
        $message = "Commands:\nHELP\n";
        if (iscreditenabled()) {
            $message .= "CREDIT\n";
        }
        $message .= "FREE\nRENT bikenumber\nRETURN bikeno stand\nWHERE bikeno\nINFO stand\nNOTE bikeno problem\n---\nFORCERENT bikenumber\nFORCERETURN bikeno stand\nLIST stand\nLAST bikeno\nREVERT bikeno\nADD email phone fullname\nDELNOTE bikeno [pattern]\nTAG stand note for all bikes\nUNTAG stand [pattern]";
        sendSMS($number, $message);
    } else {
        $message = "Commands:\nHELP\n";
        if (iscreditenabled()) {
            $message .= "CREDIT\n";
        }
        $message .= "FREE\nRENT bikeno\nRETURN bikeno stand\nWHERE bikeno\nINFO stand\nNOTE bikeno problem description\nNOTE stand problem description";
        sendSMS($number, $message);
    }
}

function unknownCommand($number, $command)
{
    sendSMS($number, _('Error. The command')." ".$command." "._('does not exist. If you need help, send:')." HELP");
}


function validateNumber($number)
{
    if (getUser($number)) {
        return true;
    } else {
        return false;
    }
}

function info($number, $stand)
{
    $stand = strtoupper($stand);
    if (!preg_match("/^[A-Z]+[0-9]*$/", $stand)) {
        sendSMS($number, _('Stand name')." '".$stand."' "._('has not been recognized. Stands are marked by CAPITALLETTERS.'));
        return;
    }
    $result = R::getAll("SELECT * FROM stands where standName=:stand", [':stand' => $stand]);
    if (count($result)!=1) {
        sendSMS($number, _('Stand')." '$stand' "._('does not exist.'));
        return;
    }
    $standDescription = $result["standDescription"];
    $standPhoto = $result["standPhoto"];
    $standLat = round($result["latitude"], 5);
    $standLong = round($result["longitude"], 5);
    $message = $stand." - ".$standDescription;
    if ($standLong and $standLat) {
        $message .= ", GPS: ".$standLat.",".$standLong;
    }
    if ($standPhoto) {
        $message .= ", ".$standPhoto;
    }
    sendSMS($number, $message);
}

/** Validate received SMS - check message for required number of arguments
 * @param string $number sender's phone number
 * @param int $receivedargumentno number of received arguments
 * @param int $requiredargumentno number of requiredarguments
 * @param string $errormessage error message to send back in case of mismatch
**/
function validateReceivedSMS($number, $receivedargumentno, $requiredargumentno, $errormessage)
{
    global $sms;
    if ($receivedargumentno < $requiredargumentno) {
        sendSMS($number, _('Error. More arguments needed, use command')." ".$errormessage);
        $sms->Respond();
        exit;
    }
   // if more arguments provided than required, they will be silently ignored
    return true;
}

function freeBikes($number)
{
    $userId = getUser($number);

    $result = R::getAll("SELECT count(bikeNum) as bikeCount,placeName from bikes join stands on bikes.currentStand=stands.standId where stands.serviceTag=0 group by placeName having bikeCount>0 order by placeName");
    $rentedBikes = count($result);

    if ($rentedBikes == 0) {
        $listBikes = _('No free bikes.');
    } else {
        $listBikes = _('Free bikes counts').":";
    }

    $listBikes = "";
    foreach ($result as $row) {
        $listBikes .= $row["placeName"].":".$row["bikeCount"];
        $listBikes .= ",";
    }
    if ($rentedBikes > 1) {
        $listBikes = substr($listBikes, 0, strlen($listBikes)-1);
    }

    $result = R::getAll("SELECT count(bikeNum) as bikeCount,placeName from bikes right join stands on bikes.currentStand=stands.standId where stands.serviceTag=0 group by placeName having bikeCount=0 order by placeName");
    $rentedBikes = count($result);

    if ($rentedBikes != 0) {
        $listBikes .= " "._('Empty stands').": ";
    }

    foreach ($result as $row) {
        $listBikes .= $row["placeName"];
        $listBikes .= ",";
    }
    if ($rentedBikes > 1) {
        $listBikes = substr($listBikes, 0, strlen($listBikes)-1);
    }

    sendSMS($number, $listBikes);
}

function log_sms($sms_uuid, $sender, $receive_time, $sms_text, $ip)
{
    $result = R::getAll("SELECT sms_uuid FROM received WHERE sms_uuid=:sms_uuid", [':sms_uuid' => $sms_uuid]);
    if (DEBUG===false and count($result)>=1) { // sms already exists in DB, possible problem
           notifyAdmins(_('Problem with SMS')." $sms_uuid!", 1);
        return false;
    } else {
        $result = R::exec(
            "INSERT INTO received SET sms_uuid=:sms_uuid, sender=:sender, receive_time=:receive_time, sms_text=:sms_text, ip=:ip",
            [':sms_uuid' => $sms_uuid, ':sender' => $sender, ':receive_time' => $receive_time, ':sms_text' => $sms_text, ':ip' => $ip]
        );
    }
}

function delnote($number, $bikeNum, $message)
{
    $userId = getUser($number);

    $bikeNum = trim($bikeNum);
    if (preg_match("/^[0-9]*$/", $bikeNum)) {
        $bikeNum = intval($bikeNum);
    } elseif (preg_match("/^[A-Z]+[0-9]*$/i", $bikeNum)) {
        $standName = $bikeNum;
        delstandnote($number, $standName, $message);
        return;
    } else {
        sendSMS($number, _('Error in bike number / stand name specification:'.$bikeNum));
        return;
    }

    $bikeNum = intval($bikeNum);

    checkUserPrivileges($number);

    $result = R::getAll(
        "SELECT number,userName,stands.standName FROM bikes ".
        "LEFT JOIN users on bikes.currentUser=users.userID ".
        "LEFT JOIN stands ON bikes.currentStand=stands.standId ".
        "WHERE bikeNum=:bikeNum", [':bikeNum' => $bikeNum]
    );
    if (count($result) != 1) {
        sendSMS($number, _('Bike')." ".$bikeNum." "._('does not exist').".");
        return;
    }

    $row = $result[0];
    $phone = $row["number"];
    $userName = $row["userName"];
    $standName = $row["standName"];

    if ($standName != null) {
        $bikeStatus = "B.$bikeNum "._('is at')." $standName.";
    } else {
        $bikeStatus = "B.$bikeNum "._('is rented by')." $userName (+$phone).";
    }

    $result = R::getAll("SELECT userName FROM users WHERE number=:number", [':number' => $number]);
    $row = $result[0];
    $reportedBy = $row["userName"];
    $matches = explode(" ", $message, 3);
    $userNote = trim($matches[2]);

    if ($userNote == '') {
        $userNote = '%';
    }

    $result = R::exec(
        "UPDATE notes SET deleted=NOW() where bikeNum=:bikeNum and deleted is null and note like :userNote",
        [':userNote' => '%'.$userNote.'%', ':bikeNum' => $bikeNum]
    );
    $count = R::getAffectedRows();

    if ($count == 0) {
        if ($userNote == "%") {
            sendSMS($number, _('No notes found for bike')." ".$bikeNum." "._('to delete').".");
        } else {
            sendSMS($number, _('No notes matching pattern')." '".$userNote."' "._('found for bike')." ".$bikeNum." "._('to delete').".");
        }
    } else {
            //only admins can delete and those will receive the confirmation in the next step.
            //sendSMS($number,"Note for bike $bikeNum deleted.");
        if ($userNote == "%") {
            notifyAdmins(_('All')." ".sprintf(ngettext('%d note', '%d notes', $count), $count)." "._('for bike')." ".$bikeNum." "._('deleted by')." ".$reportedBy.".");
        } else {
            notifyAdmins(sprintf(ngettext('%d note', '%d notes', $count), $count)." "._('for bike')." ".$bikeNum." "._('matching')." '".$userNote."' "._('deleted by')." ".$reportedBy.".");
        }
    }
}

function untag($number, $standName, $message)
{

    global $db;
    $userId = getUser($number);

    checkUserPrivileges($number);
    $result = R::getAll("SELECT standId FROM stands where standName=:standName", [':standName' => $standName]);
    if (count($result) != 1) {
        sendSMS($number, _("Stand")." ".$standName._("does not exist").".");
        return;
    }

    $row = $result[0];
    $standId = $row["standId"];

    $result = R::getAll("SELECT userName FROM users WHERE number=:number", [':number' => $number]);
    $row = $result[0];
    $reportedBy = $row["userName"];

    $matches = explode(" ", $message, 3);
    $userNote = trim($matches[2]);

    if ($userNote == '') {
        $userNote = '%';
    }

    $result = R::exec("UPDATE notes JOIN bikes ON notes.bikeNum = bikes.bikeNum ".
                      "SET deleted=now() WHERE bikes.currentStand=:standId AND note LIKE :userNote AND deleted is null",
                      [':standId' => $standId, ':userNote' => '%'.$userNote.'%']);

    $count = R::getAffectedRows();
    if ($count == 0) {
        if ($userNote == "%") {
            sendSMS($number, _('No bikes with notes found for stand')." ".$standName." "._('to delete').".");
        } else {
            sendSMS($number, _('No notes matching pattern')." '".$userNote."' "._('found for bikes on stand')." ".$standName." "._('to delete').".");
        }
    } else {
            //only admins can delete and those will receive the confirmation in the next step.
            //sendSMS($number,"Note for bike $bikeNum deleted.");
        if ($userNote == "%") {
            notifyAdmins(_('All')." ".sprintf(ngettext('%d note', '%d notes', $count), $count)." "._('for bikes on stand')." ".$standName." "._('deleted by')." ".$reportedBy.".");
        } else {
            notifyAdmins(sprintf(ngettext('%d note', '%d notes', $count), $count)." "._('for bikes on stand')." ".$standName." "._('matching')." '".$userNote."' "._('deleted by')." ".$reportedBy.".");
        }
    }
}

function delstandnote($number, $standName, $message)
{
    $userId = getUser($number);

    checkUserPrivileges($number);
    $result = R::getAll("SELECT standId FROM stands where standName=:standName", [':standName' => $standName]);
    if (count($result) != 1) {
        sendSMS($number, _("Stand")." ".$standName._("does not exist").".");
        return;
    }

    $row = $result[0];
    $standId=$row["standId"];

    $result = R::getAll("SELECT userName FROM users WHERE number=:number", [':number' => $number]);
    $row = $result[0];
    $reportedBy=$row["userName"];


    $matches=explode(" ", $message, 3);
    $userNote=trim($matches[2]);

    if ($userNote=='') {
        $userNote='%';
    }

    $result = R::exec("UPDATE notes SET deleted=NOW() where standId=:standId and deleted is null and note like '%:userNote%'",
                    [':standId' => $standId, ':userNote' => $userNote]);
    $count = R::Affected_Rows();

    if ($count == 0) {
        if ($userNote=="%") {
            sendSMS($number, _('No notes found for stand')." ".$standName." "._('to delete').".");
        } else {
            sendSMS($number, _('No notes matching pattern')." '".$userNote."' "._('found on stand')." ".$standName." "._('to delete').".");
        }
    } else {
            //only admins can delete and those will receive the confirmation in the next step.
            //sendSMS($number,"Note for bike $bikeNum deleted.");
        if ($userNote=="%") {
            notifyAdmins(_('All')." ".sprintf(ngettext('%d note', '%d notes', $count), $count)." "._('on stand')." ".$standName." "._('deleted by')." ".$reportedBy.".");
        } else {
            notifyAdmins(sprintf(ngettext('%d note', '%d notes', $count), $count)." "._('on stand')." ".$standName." "._('matching')." '".$userNote."' "._('deleted by')." ".$reportedBy.".");
        }
    }
}

function standNote($number, $standName, $message)
{
    $userId = getuserid($number);

    $result = R::getAll("SELECT standId FROM stands where standName=:standName", [':standName' => $standName]);
    if (count($result) != 1) {
        sendSMS($number, _("Stand")." ".$standName._("does not exist").".");
        return;
    }

    $row = $result[0];
    $standId = $row["standId"];

    $reportedBy = getusername($number);

    $matches = explode(" ", $message, 3);
    $userNote = trim($matches[2]);

    if ($userNote == "") { //deletemmm
        sendSMS($number, _('Empty note for stand')." ".$standName." "._('not saved, for deleting notes use DELNOTE (for admins)').".");

       //checkUserPrivileges($number);
       // @TODO remove SMS from deleting completely?
       // $result = R::exec("UPDATE bikes SET note=NULL where bikeNum=:bikeNum", [':bikeNum' => $bikeNum]);
       // only admins can delete and those will receive the confirmation in the next step.
       // sendSMS($number,"Note for bike $bikeNum deleted.");
       // notifyAdmins("Note for bike $bikeNum deleted by $reportedBy.");
    } else {
        R::exec("INSERT INTO notes SET standId=:standId, userId=:userId, note=:userNote",
                [':standId' => $standId, ':userNote' => $userNote, ':userId' => $userId]);
        $noteid = R::getInsertID();
        sendSMS($number, _('Note for stand')." ".$standName." "._('saved').".");
        notifyAdmins(_('Note #').$noteid.": "._("on stand")." ".$standName." "._('by')." ".$reportedBy." (".$number."):".$userNote);
    }
}

function tag($number, $standName, $message)
{
    $userId = getUser($number);

    $result = R::getAll("SELECT standId FROM stands where standName=:standName", [':standName' => $standName]);
    if (count($result) != 1) {
        sendSMS($number, _("Stand")." ".$standName._("does not exist").".");
        return;
    }

    $row = $result[0];
    $standId = $row["standId"];

    $result = R::getAll("SELECT userName from users where number=:number", [':number' => $number]);
    $row = $result[0];
    $reportedBy = $row["userName"];

    $matches = explode(" ", $message, 3);
    $userNote = trim($matches[2]);

    if ($userNote == "") { //deletemmm
        sendSMS($number, _('Empty tag for stand')." ".$standName." "._('not saved, for deleting notes for all bikes on stand use UNTAG (for admins)').".");

       // checkUserPrivileges($number);
       // @TODO remove SMS from deleting completely?
       // $result = R::exec("UPDATE bikes SET note=NULL where bikeNum=:bikeNum", [':bikeNum' => $bikeNum]);
       // only admins can delete and those will receive the confirmation in the next step.
       // sendSMS($number,"Note for bike $bikeNum deleted.");
       // notifyAdmins("Note for bike $bikeNum deleted by $reportedBy.");
    } else {
        R::exec("INSERT INTO notes (bikeNum,userId,note) SELECT bikeNum, :userId, :userNote FROM bikes where currentStand=:standId",
                [':standId' => $standId, ':userNote' => $userNote, ':userId' => $userId]);
        // $noteid=R::getInsertID();
        sendSMS($number, _('All bikes on stand')." ".$standName." "._('tagged').".");
        notifyAdmins(_('All bikes on stand')." "."$standName".' '._('tagged by')." ".$reportedBy." (".$number.")". _("with note:").$userNote);
    }
}

function note($number, $bikeNum, $message)
{
    $userId = getUser($number);

    $bikeNum = trim($bikeNum);
    if (preg_match("/^[0-9]*$/", $bikeNum)) {
        $bikeNum = intval($bikeNum);
    } elseif (preg_match("/^[A-Z]+[0-9]*$/i", $bikeNum)) {
        $standName = $bikeNum;
        standnote($number, $standName, $message);
        return;
    } else {
        sendSMS($number, _('Error in bike number / stand name specification:'.$bikeNum));
        return;
    }

    $bikeNum = intval($bikeNum);

    $result = R::getAll("SELECT number, userName, stands.standName FROM bikes ".
                        "LEFT JOIN users on bikes.currentUser=users.userID ".
                        "LEFT JOIN stands on bikes.currentStand=stands.standId where bikeNum=:bikeNum", [':bikeNum' => $bikeNum]);
    if (count($result) != 1) {
        sendSMS($number, _('Bike')." ".$bikeNum." "._('does not exist').".");
        return;
    }
    $row = $result[0];
    $phone = $row["number"];
    $userName = $row["userName"];
    $standName = $row["standName"];

    if ($standName != null) {
        $bikeStatus = "B.$bikeNum "._('is at')." ".$standName.".";
    } else {
        $bikeStatus = "B.$bikeNum "._('is rented')." by ".$userName." (+".$phone.").";
    }

    $result = R::getAll("SELECT userName from users where number=:number", [':number' => $number]);
    $row = $result[0];
    $reportedBy = $row["userName"];

    if (trim(strtoupper(preg_replace('/[0-9]+/', '', $message))) == "NOTE") { // blank, delete note
        $userNote = "";
    } else {
        $matches = explode(" ", $message, 3);
        $userNote = trim($matches[2]);
    }

    if ($userNote == "") {
        sendSMS($number, _('Empty note for bike')." ".$bikeNum." "._('not saved, for deleting notes use DELNOTE (for admins)').".");
       /*checkUserPrivileges($number);
       sendSMS($number,_('Empty note for bike')." ".$bikeNum." "._('not saved, for deleting notes use DELNOTE.').".");

     // @TODO remove SMS from deleting completely?
       $result = R::exec("UPDATE bikes SET note=NULL where bikeNum=:bikeNum", [':bikeNum' => $bikeNum]);
       //only admins can delete and those will receive the confirmation in the next step.
       //sendSMS($number,"Note for bike $bikeNum deleted.");
       notifyAdmins(_('Note for bike')." ".$bikeNum." "._('deleted by')." ".$reportedBy.".");
       */
    } else {
        R::exec("INSERT INTO notes SET bikeNum=:bikeNum,userId=:userId,note=:userNote",
                [':userNote' => $userNote, ':userId' => $userId, ':bikeNum' => $bikeNum]);
        $noteid = R::getInsertID();
        sendSMS($number, _('Note for bike')." ".$bikeNum." "._('saved').".");
        notifyAdmins(_('Note #').$noteid.": b.".$bikeNum." (".$bikeStatus.") "._('by')." ".$reportedBy." (".$number."):".$userNote);
    }
}

function last($number, $bike)
{
    $userId = getUser($number);
    $bikeNum = intval($bike);

    $result = R::getAll("SELECT bikeNum FROM bikes where bikeNum=:bikeNum", [':bikeNum' => $bikeNum]);
    if (count($result) != 1) {
        sendSMS($number, _('Bike')." ".$bikeNum." "._('does not exist').".");
        return;
    }

    $result = R::getAll("SELECT userName,parameter,standName,action FROM `history` ".
                        "JOIN users ON history.userid=users.userid ".
                        "LEFT JOIN stands ON stands.standid=history.parameter ".
                        "WHERE bikenum=:bikeNum AND action IN ('RETURN','RENT','REVERT') ".
                        "ORDER BY time DESC LIMIT 10", [':bikeNum' => $bikeNum]);

    $historyInfo = "B.$bikeNum:";
    foreach ($result as $row) {
        if (($standName = $row["standName"]) != null) {
            if ($row["action"] == "REVERT") {
                $historyInfo .= "*";
            }
            $historyInfo .= $standName;
        } else {
            $historyInfo .= $row["userName"]."(".$row["parameter"].")";
        }
        if (count($result) > 1) {
            $historyInfo .= ",";
        }
    }
    if ($rentedBikes > 1) {
        $historyInfo = substr($historyInfo, 0, strlen($historyInfo)-1);
    }

    sendSMS($number, $historyInfo);
}

function revert($number, $bikeNum)
{
    $userId = getUser($number);

    $result = R::getAll("SELECT currentUser FROM bikes WHERE bikeNum=:bikeNum AND currentUser<>'NULL'", [':bikeNum' => $bikeNum]);
    if (count($result) == 0) {
           sendSMS($number, _('Bike')." ".$bikeNum." "._('is not rented right now. Revert not successful!'));
        return;
    } else {
        $row = $result[0];
        $revertusernumber = getphonenumber($row["currentUser"]);
    }

    $result = R::getAll("SELECT parameter,standName FROM stands LEFT JOIN history ON stands.standId=parameter ".
                        "WHERE bikeNum=:bikeNum AND action IN ('RETURN','FORCERETURN') ".
                        "ORDER BY time DESC LIMIT 1", [':bikeNum' => $bikeNum]);
    if (count($result) == 1) {
        $row = $result[0];
        $standId = $row["parameter"];
        $stand = $row["standName"];
    }
    $result = R::getAll("SELECT parameter FROM history WHERE bikeNum=:bikeNum AND action IN ('RENT','FORCERENT') ORDER BY time DESC LIMIT 1, 1",
                        [':bikeNum' => $bikeNum]);
    if (count($result) == 1) {
        $row = $result[0];
        $code = $row["parameter"];
    }
    if ($standId and $code) {
        $result=R::exec("UPDATE bikes SET currentUser=NULL,currentStand=:standId,currentCode=:code WHERE bikeNum=:bikeNum",
                        [':standId' => $standId, ':code' => $code, ':bikeNum' => $bikeNum]);
        $result=R::exec("INSERT INTO history SET userId=:userId,bikeNum=:bikeNum,action='REVERT',parameter=':standId|:code'",
                        [':standId' => $standId, ':code' => $code, ':userId' => $userId, ':bikeNum' => $bikeNum]);
        $result=R::exec("INSERT INTO history SET userId=0,bikeNum=:bikeNum,action='RENT',parameter=:code",
                        [':code' => $code, ':bikeNum' => $bikeNum]);
        $result=R::exec("INSERT INTO history SET userId=0,bikeNum=:bikeNum,action='RETURN',parameter=:standId",
                        [':standId' => $standId, ':bikeNum' => $bikeNum]);
        sendSMS($number, _('Bike')." ".$bikeNum." "._('reverted to stand')." ".$stand." "._('with code')." ".$code.".");
        sendSMS($revertusernumber, _('Bike')." ".$bikeNum." "._('has been returned. You can now rent a new bicycle.'));
    } else {
        sendSMS($number, _('No last code for bicycle')." ".$bikeNum." "._('found. Revert not successful!'));
    }

}

function add($number, $email, $phone, $message)
{
    global $countrycode;

    $userId = getUser($number);
    $phone = normalizephonenumber($phone);

    $result = R::getAll("SELECT number,mail,userName FROM users where number=:phone OR mail=:email", [':phone' => $phone, ':email' => $email]);
    if (count($result) != 0) {
        $row = $result[0];

        $oldPhone = $row["number"];
        $oldName = $row["userName"];
        $oldMail = $row["mail"];

        sendSMS($number, _('Contact information conflict: This number already registered:')." ".$oldMail." +".$oldPhone." ".$oldName);
        return;
    }

    if ($phone < $countrycode."000000000" || $phone > ($countrycode+1)."000000000"
        || !preg_match("/add\s+([a-z0-9._%+-]+@[a-z0-9.-]+)\s+\+?[0-9]+\s+(.{2,}\s.{2,})/i", $message, $matches)) {
        sendSMS($number, _('Contact information is in incorrect format. Use:')." ADD king@earth.com 0901456789 Martin Luther King Jr.");
        return;
    }
    $userName = trim($matches[2]);
    $email = trim($matches[1]);

    $result = R::exec("INSERT into users SET userName=:userName, number=:phone, mail=:email",
                      [':userName' => $userName, ':phone' => $phone, ':email' => $email]);

    sendConfirmationEmail($email);

    sendSMS($number, _('User')." ".$userName." "._('added. They need to read email and agree to rules before using the system.'));
}

function checkUserPrivileges($number)
{
    global $sms;
    $userId = getUser($number);
    $privileges = getPrivileges($userId);
    if ($privileges == 0) {
        sendSMS($number, _('Sorry, this command is only available for the privileged users.'));
        $sms->Respond();
        exit;
    }
}
