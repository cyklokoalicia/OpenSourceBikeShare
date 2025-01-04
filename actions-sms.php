<?php

require_once 'vendor/autoload.php';
require("common.php");

function unknownCommand($number,$command)
{
   global $smsSender;
   $smsSender->send($number,_('Error. The command')." ".$command." "._('does not exist. If you need help, send:')." HELP");
}

/** Validate received SMS - check message for required number of arguments
 * @param string $number sender's phone number
 * @param int $receivedargumentno number of received arguments
 * @param int $requiredargumentno number of requiredarguments
 * @param string $errormessage error message to send back in case of mismatch
**/
function validateReceivedSMS($number, $receivedargumentno, $requiredargumentno, $errormessage)
{
    global $db, $sms, $smsSender;
    if ($receivedargumentno < $requiredargumentno) {
        $smsSender->send($number, _('Error. More arguments needed, use command') . " " . $errormessage);
        $sms->respond();
        exit;
    }
    // if more arguments provided than required, they will be silently ignored
    return TRUE;
}

function listBikes($number,$stand)
{

   global $db, $configuration, $smsSender, $user;
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

    if ($configuration->get('forcestack')) {
        $stacktopbike = checktopofstack($standId);
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

    global $db, $smsSender, $user, $phonePurifier, $configuration;
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

    if (
        $phone < $configuration->get('countrycode') . "000000000"
        || $phone > ($configuration->get('countrycode') + 1) . "000000000"
        || !preg_match("/add\s+([a-z0-9._%+-]+@[a-z0-9.-]+)\s+\+?[0-9]+\s+(.{2,}\s.{2,})/i", $message, $matches)
    ) {
        $smsSender->send($number, _('Contact information is in incorrect format. Use:') . " ADD king@earth.com 0901456789 Martin Luther King Jr.");
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
