<?php

use BikeShare\Db\DbInterface;
use BikeShare\Db\MysqliDb;

require_once 'vendor/autoload.php';
require("common.php");

function help($number)
{
    global $db, $smsSender, $user, $creditSystem;
    $userid = $user->findUserIdByNumber($number);
    $privileges = $user->findPrivileges($userid);
   if ($privileges>0)
      {
      $message="Commands:\nHELP\n";
      if ($creditSystem->isEnabled()) {
          $message.="CREDIT\n";
      }
      $message.="FREE\nRENT bikenumber\nRETURN bikeno stand\nWHERE bikeno\nINFO stand\nNOTE bikeno problem\n---\nFORCERENT bikenumber\nFORCERETURN bikeno stand\nLIST stand\nLAST bikeno\nREVERT bikeno\nADD email phone fullname\nDELNOTE bikeno [pattern]\nTAG stand note for all bikes\nUNTAG stand [pattern]";
      $smsSender->send($number,$message);
      }
   else
      {
      $message="Commands:\nHELP\n";
      if ($creditSystem->isEnabled()) {
          $message.="CREDIT\n";
      }
      $message.="FREE\nRENT bikeno\nRETURN bikeno stand\nWHERE bikeno\nINFO stand\nNOTE bikeno problem description\nNOTE stand problem description";
      $smsSender->send($number,$message);
      }
}

function unknownCommand($number,$command)
{
   global $smsSender;
   $smsSender->send($number,_('Error. The command')." ".$command." "._('does not exist. If you need help, send:')." HELP");
}

function validateNumber($number)
{
    global $user;

    return !empty($user->findUserIdByNumber($number));
}

function info($number,$stand)
{
        global $db, $smsSender;
        $stand = strtoupper($stand);

        if (!preg_match("/^[A-Z]+[0-9]*$/",$stand))
        {
                $smsSender->send($number,_('Stand name')." '".$stand."' "._('has not been recognized. Stands are marked by CAPITALLETTERS.'));
                return;
        }
        $result=$db->query("SELECT standId FROM stands where standName='$stand'");
                if ($result->num_rows!=1)
                {
                        $smsSender->send($number,_('Stand')." '$stand' "._('does not exist.'));
                        return;
                }
                $row =$result->fetch_assoc();
                $standId =$row["standId"];
        $result=$db->query("SELECT * FROM stands where standname='$stand'");
                $row =$result->fetch_assoc();
                $standDescription=$row["standDescription"];
                $standPhoto=$row["standPhoto"];
                $standLat=round($row["latitude"],5);
                $standLong=round($row["longitude"],5);
                $message=$stand." - ".$standDescription;
                if ($standLong AND $standLat) $message.=", GPS: ".$standLat.",".$standLong;
                if ($standPhoto) $message.=", ".$standPhoto;
                $smsSender->send($number,$message);

}

/** Validate received SMS - check message for required number of arguments
 * @param string $number sender's phone number
 * @param int $receivedargumentno number of received arguments
 * @param int $requiredargumentno number of requiredarguments
 * @param string $errormessage error message to send back in case of mismatch
**/
function validateReceivedSMS($number,$receivedargumentno,$requiredargumentno,$errormessage)
{
   global $db, $sms, $smsSender;
   if ($receivedargumentno<$requiredargumentno)
      {
      $smsSender->send($number,_('Error. More arguments needed, use command')." ".$errormessage);
      $sms->respond();
      exit;
      }
   // if more arguments provided than required, they will be silently ignored
   return TRUE;
}

function credit($number)
{
    global $smsSender, $user, $creditSystem;

    $userId = $user->findUserIdByNumber($number);
    $userRemainingCredit = $creditSystem->getUserCredit($userId) . $creditSystem->getCreditCurrency();

    $smsSender->send($number, _('Your remaining credit:') . " " . $userRemainingCredit);
}

function where($number,$bike)
{

   global $db, $smsSender, $user;
   $userId = $user->findUserIdByNumber($number);
   $bikeNum = intval($bike);

   $result=$db->query("SELECT number,userName,stands.standName FROM bikes LEFT JOIN users on bikes.currentUser=users.userID LEFT JOIN stands on bikes.currentStand=stands.standId where bikeNum=$bikeNum");
   if ($result->num_rows!=1)
      {
      $smsSender->send($number,_('Bike')." ".$bikeNum." "._('does not exist').".");
      return;
      }
   $row =$result->fetch_assoc();
   $phone=$row["number"];
   $userName=$row["userName"];
   $standName=$row["standName"];
   $result=$db->query("SELECT note FROM notes WHERE bikeNum=$bikeNum AND deleted IS NULL ORDER BY time DESC LIMIT 1");
   $row=$result->fetch_assoc();
   $note=$row["note"];
   if ($note)
      {
      $note=" "._('Bike note').": $note";
      }

   if ($standName!=NULL)
      {
      $smsSender->send($number,_('Bike')." ".$bikeNum." "._('is at stand')." ".$standName.$note);
      }
   else
      {
      $smsSender->send($number,_('Bike')." ".$bikeNum." "._('is rented by')." ".$userName." (+".$phone.").".$note);
      }

}


function listBikes($number,$stand)
{

   global $db,$forcestack, $smsSender, $user;
   $stacktopbike=FALSE;
   $userId = $user->findUserIdByNumber($number);
   $stand = strtoupper($stand);

   if (!preg_match("/^[A-Z]+[0-9]*$/",$stand))
   {
      $smsSender->send($number,_('Stand name')." '$stand' "._('has not been recognized. Stands are marked by CAPITALLETTERS.'));
      return;
   }

   $result=$db->query("SELECT standId FROM stands WHERE standName='$stand'");
   if ($result->num_rows!=1)
      {
      $smsSender->send($number,_('Stand')." '$stand' "._('does not exist').".");
      return;
      }
    $row=$result->fetch_assoc();
    $standId=$row["standId"];

   if ($forcestack)
            {
            $stacktopbike=checktopofstack($standId);
            }

   $result=$db->query("SELECT bikeNum FROM bikes where currentStand=$standId ORDER BY bikeNum");
   $rentedBikes=$result->num_rows;

   if ($rentedBikes==0)
      {
      $smsSender->send($number,_('Stand')." ".$stand." "._('is empty').".");
      return;
      }

   $listBikes="";
  while ($row=$result->fetch_assoc())
    {
    $listBikes.=$row["bikeNum"];
    if ($stacktopbike==$row["bikeNum"]) $listBikes.=" "._('(first)');
    $listBikes.=",";
    }
   if ($rentedBikes>1) $listBikes=substr($listBikes,0,strlen($listBikes)-1);

   $smsSender->send($number,sprintf(ngettext('%d bike','%d bikes',$rentedBikes),$rentedBikes)." "._('on stand')." ".$stand.": ".$listBikes);
}


function freeBikes($number)
{

   global $db, $smsSender, $user;
   $userId = $user->findUserIdByNumber($number);

   $result=$db->query("SELECT count(bikeNum) as bikeCount,placeName from bikes join stands on bikes.currentStand=stands.standId where stands.serviceTag=0 group by placeName having bikeCount>0 order by placeName");
   $rentedBikes=$result->num_rows;

   if ($rentedBikes==0)
   {
   	$listBikes=_('No free bikes.');
   }
   else $listBikes=_('Free bikes counts').":";

   $listBikes="";
   while ($row=$result->fetch_assoc())
      {
      $listBikes.=$row["placeName"].":".$row["bikeCount"];
      $listBikes.=",";
      }
   if ($rentedBikes>1) $listBikes=substr($listBikes,0,strlen($listBikes)-1);

   $result=$db->query("SELECT count(bikeNum) as bikeCount,placeName from bikes right join stands on bikes.currentStand=stands.standId where stands.serviceTag=0 group by placeName having bikeCount=0 order by placeName");
   $rentedBikes=$result->num_rows;

   if ($rentedBikes!=0)
   {
        $listBikes.=" "._('Empty stands').": ";
   }

   while ($row=$result->fetch_assoc())
      {
      $listBikes.=$row["placeName"];
      $listBikes.=",";
      }
   if ($rentedBikes>1) $listBikes=substr($listBikes,0,strlen($listBikes)-1);

   $smsSender->send($number,$listBikes);
}

function log_sms($sms_uuid, $sender, $receive_time, $sms_text, $ip)
{
    global $dbserver, $dbuser, $dbpassword, $dbname, $logger;
    /**
     * @var DbInterface
     */
    $localdb = new MysqliDb($dbserver, $dbuser, $dbpassword, $dbname, $logger);
    $localdb->connect();

    #TODO does it needed???
    $localdb->setAutocommit(true);

    $sms_uuid = $localdb->escape($sms_uuid);
    $sender = $localdb->escape($sender);
    $receive_time = $localdb->escape($receive_time);
    $sms_text = $localdb->escape($sms_text);
    $ip = $localdb->escape($ip);

    $result = $localdb->query("SELECT sms_uuid FROM received WHERE sms_uuid='$sms_uuid'");
    if (DEBUG === FALSE and $result->num_rows >= 1) {
        // sms already exists in DB, possible problem
        notifyAdmins(_('Problem with SMS') . " $sms_uuid!", 1);
        return FALSE;
    } else {
        $result = $localdb->query("INSERT INTO received SET sms_uuid='$sms_uuid',sender='$sender',receive_time='$receive_time',sms_text='$sms_text',ip='$ip'");
    }
}



function delnote($number,$bikeNum,$message)
{

   global $db, $smsSender, $user;
   $userId = $user->findUserIdByNumber($number);
   
    $bikeNum=trim($bikeNum);
	if(preg_match("/^[0-9]*$/",$bikeNum))
   	{
		$bikeNum = intval($bikeNum);
   	}
	else if (preg_match("/^[A-Z]+[0-9]*$/i",$bikeNum))
	{
		$standName = $bikeNum;
		delstandnote($number,$standName,$message);
		return;
	}
	else
	{
      	$smsSender->send($number,_('Error in bike number / stand name specification:'.$db->escape($bikeNum)));
		return;
	}
	   
   $bikeNum = intval($bikeNum);

   checkUserPrivileges($number);

   $result=$db->query("SELECT number,userName,stands.standName FROM bikes LEFT JOIN users on bikes.currentUser=users.userID LEFT JOIN stands ON bikes.currentStand=stands.standId WHERE bikeNum=$bikeNum");
   if ($result->num_rows!=1)
      {
      $smsSender->send($number,_('Bike')." ".$bikeNum." "._('does not exist').".");
      return;
      }
   $row =$result->fetch_assoc();
   $phone=$row["number"];
   $userName=$row["userName"];
   $standName=$row["standName"];

   if ($standName!=NULL)
      {
      $bikeStatus = "B.$bikeNum "._('is at')." $standName.";
      }
   else
      {
      $bikeStatus = "B.$bikeNum "._('is rented by')." $userName (+$phone).";
      }

   $result=$db->query("SELECT userName FROM users WHERE number=$number");
   $row =$result->fetch_assoc();
   $reportedBy=$row["userName"];

      $matches=explode(" ",$message,3);
      $userNote=$db->escape(trim($matches[2]));

	if($userNote=='')
	{
		$userNote='%';
	}

      $result=$db->query("UPDATE notes SET deleted=NOW() where bikeNum=$bikeNum and deleted is null and note like '%$userNote%'");
      $count = $db->getAffectedRows();

	if($count == 0)
	{
      		if($userNote=="%")
		{
		    $smsSender->send($number,_('No notes found for bike')." ".$bikeNum." "._('to delete').".");
		}
		else
		{
		    $smsSender->send($number,_('No notes matching pattern')." '".$userNote."' "._('found for bike')." ".$bikeNum." "._('to delete').".");
		}
	}
	else
	{
      		//only admins can delete and those will receive the confirmation in the next step.
      		//$smsSender->send($number,"Note for bike $bikeNum deleted.");
      		if($userNote=="%")
		{
			notifyAdmins(_('All')." ".sprintf(ngettext('%d note','%d notes',$count),$count)." "._('for bike')." ".$bikeNum." "._('deleted by')." ".$reportedBy.".");
		}
		else
		{
			notifyAdmins(sprintf(ngettext('%d note','%d notes',$count),$count)." "._('for bike')." ".$bikeNum." "._('matching')." '".$userNote."' "._('deleted by')." ".$reportedBy.".");
		}
      	}
}


function untag($number,$standName,$message)
{

   global $db, $smsSender, $user;
   $userId = $user->findUserIdByNumber($number);

	checkUserPrivileges($number);
	$result=$db->query("SELECT standId FROM stands where standName='$standName'");
	if ($result->num_rows!=1)
    {
      $smsSender->send($number,_("Stand")." ".$standName._("does not exist").".");
      return;
    }

   $row =$result->fetch_assoc();
   $standId=$row["standId"];
    
   $result=$db->query("SELECT userName FROM users WHERE number=$number");
   $row =$result->fetch_assoc();
   $reportedBy=$row["userName"];


      $matches=explode(" ",$message,3);
      $userNote=$db->escape(trim($matches[2]));

	if($userNote=='')
	{
		$userNote='%';
	}

    $result=$db->query("update notes join bikes on notes.bikeNum = bikes.bikeNum set deleted=now() where bikes.currentStand='$standId' and note like '%$userNote%' and deleted is null");
    $count = $db->getAffectedRows();

	if($count == 0)
	{
      		if($userNote=="%")
		{
		    $smsSender->send($number,_('No bikes with notes found for stand')." ".$standName." "._('to delete').".");
		}
		else
		{
		    $smsSender->send($number,_('No notes matching pattern')." '".$userNote."' "._('found for bikes on stand')." ".$standName." "._('to delete').".");
		}
	}
	else
	{
      		//only admins can delete and those will receive the confirmation in the next step.
      		//$smsSender->send($number,"Note for bike $bikeNum deleted.");
      		if($userNote=="%")
		{
			notifyAdmins(_('All')." ".sprintf(ngettext('%d note','%d notes',$count),$count)." "._('for bikes on stand')." ".$standName." "._('deleted by')." ".$reportedBy.".");
		}
		else
		{
			notifyAdmins(sprintf(ngettext('%d note','%d notes',$count),$count)." "._('for bikes on stand')." ".$standName." "._('matching')." '".$userNote."' "._('deleted by')." ".$reportedBy.".");
		}
      	}
}

function delstandnote($number,$standName,$message)
{

   global $db, $smsSender, $user;
   $userId = $user->findUserIdByNumber($number);

	checkUserPrivileges($number);
	$result=$db->query("SELECT standId FROM stands where standName='$standName'");
	if ($result->num_rows!=1)
    {
      $smsSender->send($number,_("Stand")." ".$standName._("does not exist").".");
      return;
    }

   $row =$result->fetch_assoc();
   $standId=$row["standId"];
    
   $result=$db->query("SELECT userName FROM users WHERE number=$number");
   $row =$result->fetch_assoc();
   $reportedBy=$row["userName"];


      $matches=explode(" ",$message,3);
      $userNote=$db->escape(trim($matches[2]));

	if($userNote=='')
	{
		$userNote='%';
	}

      $result=$db->query("UPDATE notes SET deleted=NOW() where standId=$standId and deleted is null and note like '%$userNote%'");
      $count = $db->getAffectedRows();

	if($count == 0)
	{
      		if($userNote=="%")
		{
		    $smsSender->send($number,_('No notes found for stand')." ".$standName." "._('to delete').".");
		}
		else
		{
		    $smsSender->send($number,_('No notes matching pattern')." '".$userNote."' "._('found on stand')." ".$standName." "._('to delete').".");
		}
	}
	else
	{
      		//only admins can delete and those will receive the confirmation in the next step.
      		//$smsSender->send($number,"Note for bike $bikeNum deleted.");
      		if($userNote=="%")
		{
			notifyAdmins(_('All')." ".sprintf(ngettext('%d note','%d notes',$count),$count)." "._('on stand')." ".$standName." "._('deleted by')." ".$reportedBy.".");
		}
		else
		{
			notifyAdmins(sprintf(ngettext('%d note','%d notes',$count),$count)." "._('on stand')." ".$standName." "._('matching')." '".$userNote."' "._('deleted by')." ".$reportedBy.".");
		}
      	}
}

function standNote($number,$standName,$message)
{

   global $db, $smsSender, $user;
   $userId = $user->findUserIdByNumber($number);


	$result=$db->query("SELECT standId FROM stands where standName='$standName'");
   if ($result->num_rows!=1)
      {
      $smsSender->send($number,_("Stand")." ".$standName._("does not exist").".");
      return;
      }

   $row =$result->fetch_assoc();
   $standId=$row["standId"];

   $result=$db->query("SELECT userName from users where number=$number");
   $row =$result->fetch_assoc();
   $reportedBy=$row["userName"];


    $matches=explode(" ",$message,3);
    $userNote=$db->escape(trim($matches[2]));

   if ($userNote=="") //deletemmm
      {
      		$smsSender->send($number,_('Empty note for stand')." ".$standName." "._('not saved, for deleting notes use DELNOTE (for admins)').".");

      //checkUserPrivileges($number);
      // @TODO remove SMS from deleting completely?
      //$result=$db->query("UPDATE bikes SET note=NULL where bikeNum=$bikeNum");
      //only admins can delete and those will receive the confirmation in the next step.
      //$smsSender->send($number,"Note for bike $bikeNum deleted.");
      //notifyAdmins("Note for bike $bikeNum deleted by $reportedBy.");
      }
   else
      {
      $db->query("INSERT INTO notes SET standId='$standId',userId='$userId',note='$userNote'");
      $noteid=$db->getLastInsertId();
      $smsSender->send($number,_('Note for stand')." ".$standName." "._('saved').".");
      notifyAdmins(_('Note #').$noteid.": "._("on stand")." ".$standName." "._('by')." ".$reportedBy." (".$number."):".$userNote);
      }

}



function tag($number,$standName,$message)
{

   global $db, $smsSender, $user;
   $userId = $user->findUserIdByNumber($number);

	$result=$db->query("SELECT standId FROM stands where standName='$standName'");
   if ($result->num_rows!=1)
      {
      $smsSender->send($number,_("Stand")." ".$standName._("does not exist").".");
      return;
      }

   $row =$result->fetch_assoc();
   $standId=$row["standId"];

   $result=$db->query("SELECT userName from users where number=$number");
   $row =$result->fetch_assoc();
   $reportedBy=$row["userName"];


    $matches=explode(" ",$message,3);
    $userNote=$db->escape(trim($matches[2]));

   if ($userNote=="") //deletemmm
      {
      		$smsSender->send($number,_('Empty tag for stand')." ".$standName." "._('not saved, for deleting notes for all bikes on stand use UNTAG (for admins)').".");

      //checkUserPrivileges($number);
      // @TODO remove SMS from deleting completely?
      //$result=$db->query("UPDATE bikes SET note=NULL where bikeNum=$bikeNum");
      //only admins can delete and those will receive the confirmation in the next step.
      //$smsSender->send($number,"Note for bike $bikeNum deleted.");
      //notifyAdmins("Note for bike $bikeNum deleted by $reportedBy.");
      }
   else
      {
      $db->query("INSERT INTO notes (bikeNum,userId,note) SELECT bikeNum,'$userId','$userNote' FROM bikes where currentStand='$standId'");
      //$noteid=$db->getLastInsertId();
      $smsSender->send($number,_('All bikes on stand')." ".$standName." "._('tagged').".");
      notifyAdmins(_('All bikes on stand')." "."$standName".' '._('tagged by')." ".$reportedBy." (".$number.")". _("with note:").$userNote);
      }
}


function note($number,$bikeNum,$message)
{

   global $db, $smsSender, $user;
   $userId = $user->findUserIdByNumber($number);
   
    $bikeNum=trim($bikeNum);
	if(preg_match("/^[0-9]*$/",$bikeNum))
   	{
		$bikeNum = intval($bikeNum);
   	}
	else if (preg_match("/^[A-Z]+[0-9]*$/i",$bikeNum))
	{
		$standName = $bikeNum;
		standnote($number,$standName,$message);
		return;
	}
	else
	{
      	$smsSender->send($number,_('Error in bike number / stand name specification:'.$db->escape($bikeNum)));
		return;
	}
	   
   $bikeNum = intval($bikeNum);
   
   $result=$db->query("SELECT number,userName,stands.standName FROM bikes LEFT JOIN users on bikes.currentUser=users.userID LEFT JOIN stands on bikes.currentStand=stands.standId where bikeNum=$bikeNum");
   if ($result->num_rows!=1)
      {
      $smsSender->send($number,_('Bike')." ".$bikeNum." "._('does not exist').".");
      return;
      }
   $row =$result->fetch_assoc();
   $phone=$row["number"];
   $userName=$row["userName"];
   $standName=$row["standName"];

   if ($standName!=NULL)
      {
      $bikeStatus = "B.$bikeNum "._('is at')." ".$standName.".";
      }
   else
      {
      $bikeStatus = "B.$bikeNum "._('is rented')." by ".$userName." (+".$phone.").";
      }

   $result=$db->query("SELECT userName from users where number=$number");
   $row =$result->fetch_assoc();
   $reportedBy=$row["userName"];

   if (trim(strtoupper(preg_replace('/[0-9]+/','',$message)))=="NOTE") // blank, delete note
      {
      $userNote="";
      }
   else
      {
      $matches=explode(" ",$message,3);
      $userNote=$db->escape(trim($matches[2]));
      }

   if ($userNote=="")
      {
      $smsSender->send($number,_('Empty note for bike')." ".$bikeNum." "._('not saved, for deleting notes use DELNOTE (for admins)').".");
      /*checkUserPrivileges($number);
      $smsSender->send($number,_('Empty note for bike')." ".$bikeNum." "._('not saved, for deleting notes use DELNOTE.').".");
      
	// @TODO remove SMS from deleting completely?
      $result=$db->query("UPDATE bikes SET note=NULL where bikeNum=$bikeNum");
      //only admins can delete and those will receive the confirmation in the next step.
      //$smsSender->send($number,"Note for bike $bikeNum deleted.");
      notifyAdmins(_('Note for bike')." ".$bikeNum." "._('deleted by')." ".$reportedBy.".");
      */
	}
   else
      {
      $db->query("INSERT INTO notes SET bikeNum='$bikeNum',userId='$userId',note='$userNote'");
      $noteid=$db->getLastInsertId();
      $smsSender->send($number,_('Note for bike')." ".$bikeNum." "._('saved').".");
      notifyAdmins(_('Note #').$noteid.": b.".$bikeNum." (".$bikeStatus.") "._('by')." ".$reportedBy." (".$number."):".$userNote);
      }

}

function last($number,$bike)
{

   global $db, $smsSender, $user;
   $userId = $user->findUserIdByNumber($number);
   $bikeNum = intval($bike);

   $result=$db->query("SELECT bikeNum FROM bikes where bikeNum=$bikeNum");
          if ($result->num_rows!=1)
      {
         $smsSender->send($number,_('Bike')." ".$bikeNum." "._('does not exist').".");
         return;
      }

   $result=$db->query("SELECT userName,parameter,standName,action FROM `history` join users on history.userid=users.userid left join stands on stands.standid=history.parameter where bikenum=$bikeNum and action in ('RETURN','RENT','REVERT') order by time desc LIMIT 10");

   $historyInfo="B.$bikeNum:";
   while($row=$result->fetch_assoc())
   {
     if (($standName=$row["standName"])!=NULL)
      {
         if ($row["action"]=="REVERT") $historyInfo.="*";
         $historyInfo.=$standName;
      }
      else
      {
         $historyInfo.=$row["userName"]."(".$row["parameter"].")";
      }
      if ($result->num_rows>1) $historyInfo.=",";
   }
   if ($rentedBikes>1) $historyInfo=substr($historyInfo,0,strlen($historyInfo)-1);

   $smsSender->send($number,$historyInfo);


}

function revert($number,$bikeNum)
{

        global $db, $smsSender, $user;
        $userId = $user->findUserIdByNumber($number);

        $result=$db->query("SELECT currentUser FROM bikes WHERE bikeNum=$bikeNum AND currentUser<>'NULL'");
        if (!$result->num_rows)
           {
           $smsSender->send($number,_('Bike')." ".$bikeNum." "._('is not rented right now. Revert not successful!'));
           return;
           }
        else
           {
           $row=$result->fetch_assoc();
           $revertusernumber=$user->findPhoneNumber($row["currentUser"]);
           }

        $result=$db->query("SELECT parameter,standName FROM stands LEFT JOIN history ON stands.standId=parameter WHERE bikeNum=$bikeNum AND action IN ('RETURN','FORCERETURN') ORDER BY time DESC LIMIT 1");
        if ($result->num_rows==1)
                {
                        $row=$result->fetch_assoc();
                        $standId=$row["parameter"];
                        $stand=$row["standName"];
                }
        $result=$db->query("SELECT parameter FROM history WHERE bikeNum=$bikeNum AND action IN ('RENT','FORCERENT') ORDER BY time DESC LIMIT 1,1");
        if ($result->num_rows==1)
                {
                        $row =$result->fetch_assoc();
                        $code=$row["parameter"];
                }
        if ($standId and $code)
           {
           $result=$db->query("UPDATE bikes SET currentUser=NULL,currentStand=$standId,currentCode=$code WHERE bikeNum=$bikeNum");
           $result=$db->query("INSERT INTO history SET userId=$userId,bikeNum=$bikeNum,action='REVERT',parameter='$standId|$code'");
           $result=$db->query("INSERT INTO history SET userId=0,bikeNum=$bikeNum,action='RENT',parameter=$code");
           $result=$db->query("INSERT INTO history SET userId=0,bikeNum=$bikeNum,action='RETURN',parameter=$standId");
           $smsSender->send($number,_('Bike')." ".$bikeNum." "._('reverted to stand')." ".$stand." "._('with code')." ".$code.".");
           $smsSender->send($revertusernumber,_('Bike')." ".$bikeNum." "._('has been returned. You can now rent a new bicycle.'));
           }
        else
           {
           $smsSender->send($number,_('No last code for bicycle')." ".$bikeNum." "._('found. Revert not successful!'));
           }

}

function add($number,$email,$phone,$message)
{

    global $db, $countrycode, $smsSender, $user, $phonePurifier;
    $userId = $user->findUserIdByNumber($number); #maybe we should check if the user exist???
    $phone = $phonePurifier->purify($phone);

   $result=$db->query("SELECT number,mail,userName FROM users where number=$phone OR mail='$email'");
          if ($result->num_rows!=0)
      {
             $row =$result->fetch_assoc();

         $oldPhone=$row["number"];
         $oldName=$row["userName"];
         $oldMail=$row["mail"];

         $smsSender->send($number,_('Contact information conflict: This number already registered:')." ".$oldMail." +".$oldPhone." ".$oldName);
         return;
      }

   if ($phone < $countrycode."000000000" || $phone > ($countrycode+1)."000000000" || !preg_match("/add\s+([a-z0-9._%+-]+@[a-z0-9.-]+)\s+\+?[0-9]+\s+(.{2,}\s.{2,})/i",$message ,$matches))
   {
      $smsSender->send($number,_('Contact information is in incorrect format. Use:')." ADD king@earth.com 0901456789 Martin Luther King Jr.");
      return;
   }
   $userName=$db->escape(trim($matches[2]));
   $email=$db->escape(trim($matches[1]));

   $result=$db->query("INSERT into users SET userName='$userName',number=$phone,mail='$email'");

   sendConfirmationEmail($email);

   $smsSender->send($number,_('User')." ".$userName." "._('added. They need to read email and agree to rules before using the system.'));


}

function checkUserPrivileges($number)
{
   global $db, $sms, $smsSender, $user;
   $userId=$user->findUserIdByNumber($number);
   $privileges=$user->findPrivileges($userId);
   if ($privileges==0)
      {
      $smsSender->send($number,_('Sorry, this command is only available for the privileged users.'));
      $sms->respond();
      exit;
      }
}
