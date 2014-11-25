<?php
require("connectors/".$connectors["sms"].".php");
$sms=new SMSConnector($connectors["sms"]);

function error($message)
{
   global $db;
   $db->conn->rollback();
   exit($message);
}

function sendEmail($email,$subject,$message)
{
   global $db;
   $headers = 'From: info@whitebikes.info' . "\r\n" . 'Reply-To: info@cyklokoalicia.sk' . "\r\n" . 'X-Mailer: PHP/' . phpversion();
   if (DEBUG===FALSE) mail($email, $subject, $message, $headers); // @TODO: replace with proper SMTP mailer
   else echo $email,' | ',$subject,' | ',$message;
}

function sendSMS($number,$text)
{

   global $sms;

   $message=$text;
   if (strlen($message)>160)
      {
      $message=chunk_split($message,160,"|");
      $message=explode("|",$message);
      foreach ($message as $text)
         {
         $text=trim($text);
         if ($text)
            {
            log_sendsms($number,$text);
            if (DEBUG===TRUE)
               {
               echo $number,' -&gt ',$text,'<br />';
               }
            else
               {
               $sms->Send($number,$text);
               }
            }
         }
      }
   else
      {
      log_sendsms($number,$text);
      if (DEBUG===TRUE)
         {
         echo $number,' -&gt ',$text,'<br />';
         }
      else
         {
         $sms->Send($number,$text);
         }
      }

}

function log_sendsms($number, $text)
{
        global $dbServer,$dbUser,$dbPassword,$dbName;
        $localdb=new Database($dbServer,$dbUser,$dbPassword,$dbName);
        $localdb->connect();
        $localdb->conn->autocommit(TRUE);
        $number = $localdb->conn->real_escape_string($number);
        $text = $localdb->conn->real_escape_string($text);

        $result = $localdb->query("INSERT INTO sent SET number='$number',text='$text'");

}

function getprivileges($userid)
{
   global $db;

   $result = $db->query("SELECT privileges FROM users WHERE userId=$userid");
   if ($result->num_rows==1)
      {
      $row = $result->fetch_assoc();
      return $row["privileges"];
      }
   return FALSE;
}

function getusername($userid)
{
   global $db;

   $result = $db->query("SELECT userName FROM users WHERE userId=$userid");
   if ($result->num_rows==1)
      {
      $row = $result->fetch_assoc();
      return $row["userName"];
      }
   return FALSE;
}

function getphonenumber($userid)
{
   global $db;

   $result = $db->query("SELECT number FROM users WHERE userId=$userid");
   if ($result->num_rows==1)
      {
      $row = $result->fetch_assoc();
      return $row["number"];
      }
   return FALSE;
}

function getuserid($number)
{
   global $db;

   $result = $db->query("SELECT userId FROM users where number='$number'");
   if ($result->num_rows==1)
      {
      $row = $result->fetch_assoc();
      return $row["userId"];
      }
   return FALSE;
}

/**
 * @param int $notificationtype 0 = via SMS, 1 = via email
**/
function notifyAdmins($message,$notificationtype=0)
{
   global $db,$systemName;

   $result = $db->query("SELECT number,mail FROM users where privileges & 2 != 0");
   $admins = $result->fetch_all(MYSQLI_ASSOC);
   for ($i=0; $i<count($admins);$i++)
      {
      if ($notificationtype==0)
         {
         sendSMS($admins[$i]["number"],$message);
         }
      else
         {
         sendEmail($admins[$i]["mail"],$systemName." notification",$message);
         }
      }
}

function sendConfirmationEmail($email)
{

        global $db, $dbPassword, $systemURL;

        $subject = 'registracia/registration White Bikes';

        if ($result = $db->query("SELECT userName,userId FROM users where mail='$email'")) {
                $user = $result->fetch_all(MYSQLI_ASSOC);
        } else error("email not fetched");

        $userId =$user[0]["userId"];
        $userKey = hash('sha256', $email.$dbPassword.rand(0,1000000));

        if ($result = $db->query("INSERT into registration SET userKey='$userKey',userId='$userId'")) {
        } else error("insert registration failed");

        if ($result = $db->query("INSERT into limits SET userId='$userId',userLimit=0")) {
        } else error("insert limit failed");

                $mena = preg_split("/[\s,]+/",$user[0]["userName"]);
                $krstne = $mena[0];
                $message = "Ahoj $krstne, [EN below]\n
bol/a si zaregistrovany/a do systemu komunitneho poziciavania bicyklov White Bikes.\n
Navod k Bielym Bicyklom najdes na http://v.gd/navod

Ak suhlasis s pravidlami, klikni na linku dole v maili.

Dear $krstne,
you were registered to the community bikesharing White Bikes.
The current guide (in English) for White Bikes can be found at http://v.gd/introWB

If you agree with the rules, click on the following link:

".$systemURL."agree.php?key=$userKey
";
                sendEmail($email, $subject, $message);
}

function confirmUser($userKey)
{
        global $db;
        $userKey = $db->conn->real_escape_string($userKey);

        if ($result = $db->query("SELECT userId FROM registration where userKey='$userKey'")) {
                if($result->num_rows==1)
                {
                        $row = $result->fetch_assoc();
                        $userId = $row["userId"];
                }
                else
                {
                        echo '<div class="alert alert-danger" role="alert">Registration key not found!</div>';
                        return FALSE;
                }
        } else error("key not fetched");

        if ($result = $db->query("UPDATE limits SET userLimit=1 where userId=$userId")) {
        } else error("update limit failed");

        if ($result = $db->query("DELETE from registration where userId='$userId'")) {
        } else error("delete registration failed");

        echo '<div class="alert alert-success" role="alert">Your account has been activated. Welcome!</div>';

}

function checktopofstack($standid)
{
   global $db;
   // find current bikes at stand
   $result=$db->query("SELECT bikeNum FROM bikes LEFT JOIN stands ON bikes.currentStand=stands.standId WHERE standId='$standid'");
   while($row=$result->fetch_assoc())
      {
      $currentbikes[]=$row["bikeNum"];
      }
   // find last returned bike at stand
   $result=$db->query("SELECT bikeNum FROM history WHERE action='RETURN' AND parameter='$standid' AND bikeNum IN (".implode($currentbikes,",").") ORDER BY time DESC LIMIT 1");
   if ($result->num_rows)
      {
      $row=$result->fetch_assoc();
      return $row["bikeNum"];
      }
   return FALSE;
}

function checklongrental()
{
   global $db,$watches;

   $abusers=""; $found=0;
   $result=$db->query("SELECT bikeNum,currentUser,userName FROM bikes LEFT JOIN users ON bikes.currentUser=users.userId WHERE currentStand IS NULL");
   while($row=$result->fetch_assoc())
      {
      $bikenum=$row["bikeNum"];
      $userid=$row["currentUser"];
      $username=$row["userName"];
      $result2=$db->query("SELECT time FROM history WHERE bikeNum=$bikenum AND userId=$userid AND action='RENT' ORDER BY time DESC LIMIT 1");
      if ($result2->num_rows)
         {
         $row2=$result2->fetch_assoc();
         $time=$row2["time"];
         $time=strtotime($time);
         if ($time+($watches["longrental"]*3600)<=time())
            {
            $abusers.=" b".$bikenum." by ".$username.",";
            $found=1;
            }
         }
      }
   if ($found)
      {
      $abusers=substr($abusers,0,strlen($abusers)-1);
      notifyAdmins($watches["longrental"]."+ hour rental:".$abusers);
      }

}

// cron - called from cron by default, set to 0 if from rent function, userid needs to be passed if cron=0
function checktoomany($cron=1,$userid=0)
{
   global $db,$watches;

   $abusers=""; $found=0;

   if ($cron) // called from cron
      {
      $result=$db->query("SELECT users.userId,userName,userLimit FROM users LEFT JOIN limits ON users.userId=limits.userId");
      while($row=$result->fetch_assoc())
         {
         $userid=$row["userId"];
         $username=$row["userName"];
         $userlimit=$row["userLimit"];
         $currenttime=date("Y-m-d H:i:s",time()-$watches["timetoomany"]*3600);
         $result2=$db->query("SELECT bikeNum FROM history WHERE userId=$userid AND action='RENT' AND time>'$currenttime'");
         if ($result2->num_rows>=($userlimit+$watches["numbertoomany"]))
            {
            $abusers.=" ".$result2->num_rows." (limit ".$userlimit.") by ".$username.",";
            $found=1;
            }
         }
      }
   else // called from function for user userid
      {
      $result=$db->query("SELECT users.userId,userName,userLimit FROM users LEFT JOIN limits ON users.userId=limits.userId WHERE users.userId=$userid");
      $row=$result->fetch_assoc();
      $username=$row["userName"];
      $userlimit=$row["userLimit"];
      $currenttime=date("Y-m-d H:i:s",time()-$watches["timetoomany"]*3600);
      $result=$db->query("SELECT bikeNum FROM history WHERE userId=$userid AND action='RENT' AND time>'$currenttime'");
      if ($result->num_rows>=($userlimit+$watches["numbertoomany"]))
         {
         $abusers.=" ".$result->num_rows." (limit ".$userlimit.") by ".$username.",";
         $found=1;
         }
      }
   if ($found)
      {
      $abusers=substr($abusers,0,strlen($abusers)-1);
      notifyAdmins("Over limit in ".$watches["timetoomany"]." hs:".$abusers);
      }

}

// check if user has credit >= minimum credit+rent fee+long rental fee
function checkrequiredcredit($userid)
{
   global $db,$credit;

   if (iscreditenabled()==FALSE) return; // if credit system disabled, exit

   $requiredcredit=$credit["min"]+$credit["rent"]+$credit["longrental"];
   $result=$db->query("SELECT credit FROM credit WHERE userId=$userid AND credit>=$requiredcredit");
   if ($result->num_rows==1)
      {
      $row=$result->fetch_assoc();
      return TRUE;
      }
   return FALSE;

}

// subtract credit for rental
function changecreditendrental($bike,$userid)
{
   global $db,$watches,$credit;

   if (iscreditenabled()==FALSE) return; // if credit system disabled, exit

   $usercredit=getusercredit($userid);

   $result=$db->query("SELECT time FROM history WHERE bikeNum=$bike AND userId=$userid AND action='RENT' ORDER BY time DESC LIMIT 1");
   if ($result->num_rows==1)
      {
      $row=$result->fetch_assoc();
      $starttime=strtotime($row["time"]);
      $endtime=time();
      $timediff=$endtime-$starttime;
      $creditchange=0;
      if ($timediff>$watches["freetime"]*60) $creditchange=$creditchange+$credit["rent"];
      if ($credit["pricecycle"] AND $timediff>$watches["freetime"]*60*2) // after first paid period, i.e. freetime*2; if pricecycle enabled
         {
         $temptimediff=$timediff-($watches["freetime"]*60*2);
         if ($credit["pricecycle"]==1) // flat price per cycle
            {
            $cycles=ceil($temptimediff/($watches["flatpricecycle"]*60));
            $creditchange=$creditchange+($credit["rent"]*$cycles);
            }
         elseif ($credit["pricecycle"]==2) // double price per cycle
            {
            $cycles=ceil($temptimediff/($watches["doublepricecycle"]*60));
            $tempcreditrent=$credit["rent"];
            for ($i=1;$i<=$cycles;$i++)
               {
               $multiplier=$i;
               if ($multiplier>$watches["doublepricecyclecap"])
                  {
                  $multiplier=$watches["doublepricecyclecap"];
                  }
               // exception for rent=1, otherwise square won't work:
               if ($tempcreditrent==1) $tempcreditrent=2;
               $creditchange=$creditchange+pow($tempcreditrent,$multiplier);
               }
            }
         }
      if ($timediff>$watches["longrental"]*3600) $creditchange=$creditchange+$credit["longrental"];
      $usercredit=$usercredit-$creditchange;
      $result=$db->query("UPDATE credit SET credit=$usercredit WHERE userId=$userid");
      $result=$db->query("INSERT INTO history SET userId=$userid,bikeNum=$bike,action='CREDIT',parameter=$usercredit");
      return $creditchange;
      }

}

function iscreditenabled()
{
   global $credit;

   if ($credit["enabled"]) return TRUE;

   return FALSE;

}

function getusercredit($userid)
{
   global $db,$credit;

   if (iscreditenabled()==FALSE) return; // if credit system disabled, exit

   $result=$db->query("SELECT credit FROM credit WHERE userId=$userid");
   $row=$result->fetch_assoc();
   $usercredit=$row["credit"];

   return $usercredit;

}

function getcreditcurrency()
{
   global $credit;

   if (iscreditenabled()==FALSE) return; // if credit system disabled, exit

   return $credit["currency"];

}

?>