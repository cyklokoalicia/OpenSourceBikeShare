<?php
require("common.php");

function help($number)
{
   global $db;
   $userid=getUser($number);
   $privileges=getprivileges($userid);
   if ($privileges>0)
      {
      $message="Commands:\nHELP\n";
      if (iscreditenabled()) $message.="CREDIT\n";
      $message.="FREE\nRENT bikenumber\nRETURN bikeno stand\nWHERE bikeno\nINFO stand\nNOTE bikeno problem\n---\nFORCERENT bikenumber\nFORCERETURN bikeno stand\nLIST stand\nLAST bikeno\nREVERT bikeno\nADD email phone fullname";
      sendSMS($number,$message);
      }
   else
      {
      $message="Commands:\nHELP\n";
      if (iscreditenabled()) $message.="CREDIT\n";
      $message.="FREE\nRENT bikeno\nRETURN bikeno stand\nWHERE bikeno\nINFO stand\nNOTE bikeno problem description";
      sendSMS($number,$message);
      }
}

function unknownCommand($number,$command)
{
   global $db;
   sendSMS($number,"Error. The command $command does not exist. If you need help, send: HELP");
}

/**
 * @deprecated, call getuserid() directly
 */
function getUser($number)
{
   return getuserid($number);
}

function validateNumber($number)
{
    if (getUser($number))
   return true;
    else
   return false;
}

function info($number,$stand)
{
        global $db;
        $stand = strtoupper($stand);

        if (!preg_match("/^[A-Z]+[0-9]*$/",$stand))
        {
                sendSMS($number,"The stand name '$stand' has not been recognized. Stands are marked by CAPITALLETTERS.");
                return;
        }
        if ($result=$db->query("SELECT standId FROM stands where standName='$stand'")) {
                if ($result->num_rows!=1)
                {
                        sendSMS($number,"Stand '$stand' does not exist.");
                        return;
                }
                $row =$result->fetch_assoc();
                $standId =$row["standId"];
        } else error("stand not retrieved");
        if ($result=$db->query("SELECT * FROM stands where standname='$stand'")) {
                $row =$result->fetch_assoc();
                $standDescription=$row["standDescription"];
                $standPhoto=$row["standPhoto"];
                $standLat=round($row["latitude"],5);
                $standLong=round($row["longitude"],5);
                $message=$stand." - ".$standDescription;
                if ($standLong AND $standLat) $message.=", GPS: ".$standLong.",".$standLat;
                if ($standPhoto) $message.=", ".$standPhoto;
                sendSMS($number,$message);
        } else error("stand not found");

}

/** Validate received SMS - check message for required number of arguments
 * @param string $number sender's phone number
 * @param int $receivedargumentno number of received arguments
 * @param int $requiredargumentno number of requiredarguments
 * @param string $errormessage error message to send back in case of mismatch
**/
function validateReceivedSMS($number,$receivedargumentno,$requiredargumentno,$errormessage)
{
   global $db, $sms;
   if ($receivedargumentno<$requiredargumentno)
      {
      sendSMS($number,"Error. More arguments needed, use command ".$errormessage);
      $sms->Respond();
      exit;
      }
   // if more arguments provided than required, they will be silently ignored
   return TRUE;
}

function credit($number)
{
   global $db;
   $userid=getUser($number);
   $usercredit=getusercredit($userid).getcreditcurrency();
   sendSMS($number,"Your remaining credit: ".$usercredit);
}

function rent($number,$bike,$force=FALSE)
{

        global $db,$forcestack,$watches,$credit;
        $stacktopbike=FALSE;
   $userId = getUser($number);
   $bikeNum = intval($bike);
   $requiredcredit=$credit["min"]+$credit["rent"]+$credit["longrental"];

   if ($force==FALSE)
           {
           $creditcheck=checkrequiredcredit($userId);
            if ($creditcheck===FALSE)
               {
               $result=$db->query("SELECT credit FROM credit WHERE userId=$userId");
               $row=$result->fetch_assoc();
               sendSMS($number,"Please, recharge your credit: ".$row["credit"].$credit["currency"].". Credit required: ".$requiredcredit.$credit["currency"].".");
               return;
               }

         checktoomany(0,$userId);

         if ($result=$db->query("SELECT count(*) as countRented FROM bikes where currentUser=$userId")) {
                  $row =$result->fetch_assoc();
                  $countRented =$row["countRented"];
         } else error("count not retrieved");

         if ($result=$db->query("SELECT userLimit FROM limits where userId=$userId")) {
                  $row =$result->fetch_assoc();
                  $limit =$row["userLimit"];
         } else error("limit not retrieved");

         if ($countRented >=$limit)
         {
                  if ($limit==0)
                     {
                     sendSMS($number,"You can not rent any bikes. Contact the admins to lift the ban.");
                     }
                  elseif ($limit==1)
                     {
                     sendSMS($number,"You can only rent ".$limit." bike at once.");
                     }
                  else
                     {
                     sendSMS($number,"You can only rent ".$limit." bikes at once and you have already rented ".$limit.".");
                     }

                  return;
         }

         if ($forcestack OR $watches["stack"])
         {
            $result=$db->query("SELECT currentStand FROM bikes WHERE bikeNum='$bike'");
            $row=$result->fetch_assoc();
            $standid=$row["currentStand"];
            $stacktopbike=checktopofstack($standid);
            if ($watches["stack"] AND $stacktopbike<>$bike)
               {
               $result=$db->query("SELECT standName FROM stands WHERE standId='$standid'");
               $row=$result->fetch_assoc();
               $stand=$row["standName"];
               $user=getusername($userId);
               notifyAdmins("Bike ".$bike." rented out of stack by ".$user.". ".$stacktopbike." was on the top of the stack at ".$stand.".",1);
               }
            if ($forcestack AND $stacktopbike<>$bikeNum)
               {
               sendSMS($number,"Bike ".$bikeNum." is not rentable now, rent bike ".$stacktopbike." from here.",ERROR);
               return;
               }
            }
         }

   $result=$db->query("SELECT currentUser,currentCode FROM bikes WHERE bikeNum=$bikeNum");
   if($result->num_rows!=1)
      {
      sendSMS($number,"Bike $bikeNum does not exist.");
      return;
      }
   $row =$result->fetch_assoc();
   $currentCode = sprintf("%04d",$row["currentCode"]);
   $currentUser=$row["currentUser"];
   $result=$db->query("SELECT note FROM notes WHERE bikeNum=$bikeNum and deleted is null ORDER BY time DESC LIMIT 1");
   $row=$result->fetch_assoc();
   $note=$row["note"];
   if ($currentUser)
      {
      $result=$db->query("SELECT number FROM users WHERE userId=$currentUser");
      $row =$result->fetch_assoc();
      $currentUserNumber =$row["number"];
      }

   $newCode = sprintf("%04d",rand(100,9900));//do not create a code with more than one leading zero or more than two leading 9s (kind of unusual/unsafe).

   if ($force==FALSE)
          {
            if ($currentUser==$userId)
            {
                     sendSMS($number,"You already rented the bike $bikeNum. Code is $currentCode. Return the bike with command: RETURN bikenumber standname.");
                     return;
            }
            if ($currentUser!=0)
            {
                     sendSMS($number,"The bike $bikeNum is already rented.");
                     return;
            }
         }

   $message="Bike $bikeNum: Open with code $currentCode, change code immediately to $newCode (open,rotate metal part,set new code,rotate metal part back).";
   if ($note)
   {
      $message.="(bike note:".$note.")";
   }
   sendSMS($number,$message);

   if ($result=$db->query("UPDATE bikes SET currentUser=$userId,currentCode=$newCode,currentStand=NULL WHERE bikeNum=$bikeNum")) {
   } else error("update failed");

   if ($force==FALSE)
          {
            if ($result=$db->query("INSERT INTO history SET userId=$userId,bikeNum=$bikeNum,action='RENT',parameter=$newCode")) {
            } else error("update failed");
          }
        else
         {
           if ($result=$db->query("INSERT INTO history SET userId=$userId,bikeNum=$bikeNum,action='FORCERENT',parameter=$newCode")) {
            if ($currentUser) { sendSMS($currentUserNumber,"System override: Your rented bike $bikeNum has been rented by admin."); }
            } else error("update failed");
         }


}

function returnBike($number,$bike,$stand,$message="",$force=FALSE)
{

   global $db;
   $userId = getUser($number);
   $bikeNum = intval($bike);
   $stand = strtoupper($stand);

   if (!preg_match("/^[A-Z]+[0-9]*$/",$stand))
      {
      sendSMS($number,"The stand name '$stand' has not been recognized. Stands are named in CAPITALLETTERS.");
      return;
      }

   if ($force==FALSE)
      {
      $result=$db->query("SELECT bikeNum FROM bikes where currentUser=$userId ORDER BY bikeNum");
      $rentedBikes =$result->fetch_all(MYSQLI_ASSOC);

      if (count($rentedBikes)==0)
         {
         sendSMS($number,"You have no rented bikes currently.");
         return;
         }

      $listBikes="";
      for($i=0; $i<count($rentedBikes);$i++)
         {
         $listBikes.=$rentedBikes[$i]["bikeNum"];
         if ($i+1<count($rentedBikes)) $listBikes.=",";
         }
      }

   if ($force==FALSE)
      {
      $result=$db->query("SELECT currentCode FROM bikes WHERE currentUser=$userId AND bikeNum=$bikeNum");
      if ($result->num_rows!=1)
         {
         sendSMS($number,"You have not rented the bike $bikeNum. You have rented the following bike(s): $listBikes");
         return;
         }

      $row=$result->fetch_assoc();
      $currentCode = sprintf("%04d",$row["currentCode"]);
      $result=$db->query("SELECT note FROM notes WHERE bikeNum=$bikeNum and deleted is NULL ORDER BY time DESC LIMIT 1");
      $row=$result->fetch_assoc();
      $note=$row["note"];
      }
   else
      {
      $result=$db->query("SELECT currentCode,currentUser FROM bikes WHERE bikeNum=$bikeNum");
      if ($result->num_rows!=1)
         {
         sendSMS($number,"The bike $bikeNum is not rented. Saint Thomas, the patronus of all unrented bikes, prohibited returning unrented bikes.");
         return;
         }

      $row =$result->fetch_assoc();
      $currentCode = sprintf("%04d",$row["currentCode"]);
      $currentUser =$row["currentUser"];
      $result=$db->query("SELECT note FROM notes WHERE bikeNum=$bikeNum and deleted IS null ORDER BY time DESC LIMIT 1");
      $row=$result->fetch_assoc();
      $note=$row["note"];
      $result=$db->query("SELECT number FROM users WHERE userId=$currentUser");
      $row =$result->fetch_assoc();
      $currentUserNumber =$row["number"];
      }

   $result=$db->query("SELECT standId FROM stands where standName='$stand'");
   if ($result->num_rows!=1)
      {
      sendSMS($number,"Stand '$stand' does not exist.");
      return;
      }
      $row =$result->fetch_assoc();
      $standId =$row["standId"];

   if (!preg_match("/return[\s,\.]+[0-9]+[\s,\.]+[a-zA-Z]+[\s,\.]+(.*)/i",$message ,$matches))
      {
      $userNote="";
      }
   else $userNote=$db->conn->real_escape_string(trim($matches[1]));

   $result=$db->query("UPDATE bikes SET currentUser=NULL,currentStand=$standId where bikeNum=$bikeNum");
   if ($userNote)
      {
      $db->query("INSERT INTO notes SET bikeNum=$bikeNum,userId=$userId,note='$userNote'");
      // @TODO report note to admins !!!!
      }

   $message = "Bike $bikeNum returned to stand $stand. Make sure the code is $currentCode.";
   if ($note or $userNote)
      {
      $tempnote=$note;
      if ($userNote) $tempnote=$userNote;
      if ($tempnote) $message.="(note:".$tempnote.")";
      }
   $message.=" Rotate lockpad to 0000.";

   if ($force==FALSE)
      {
      $creditchange=changecreditendrental($bikeNum,$userId);
      $result=$db->query("INSERT INTO history SET userId=$userId,bikeNum=$bikeNum,action='RETURN',parameter=$standId");
      }
   else
      {
      $result=$db->query("INSERT INTO history SET userId=$userId,bikeNum=$bikeNum,action='FORCERETURN',parameter=$standId");
      sendSMS($currentUserNumber,"System override: Your rented bike $bikeNum has been returned by admin.");
      }

   if (iscreditenabled())
      {
      $message.="Credit: ".getusercredit($userId).getcreditcurrency();
      if ($creditchange) $message.=" (-".$creditchange.")";
      $message.=".";
      }
   sendSMS($number,$message);

}


function where($number,$bike)
{

   global $db;
   $userId = getUser($number);
   $bikeNum = intval($bike);

   $result=$db->query("SELECT number,userName,stands.standName FROM bikes LEFT JOIN users on bikes.currentUser=users.userID LEFT JOIN stands on bikes.currentStand=stands.standId where bikeNum=$bikeNum");
   if ($result->num_rows!=1)
      {
      sendSMS($number,"Bike $bikeNum does not exist.");
      return;
      }
   $row =$result->fetch_assoc();
   $phone=$row["number"];
   $userName=$row["userName"];
   $standName=$row["standName"];
   $result=$db->query("SELECT note FROM notes WHERE bikeNum=$bikeNum and deleted IS NULL ORDER BY time DESC LIMIT 1");
   $row=$result->fetch_assoc();
   $note=$row["note"];
   if ($note)
      {
      $note=" Bike note: $note";
      }

   if ($standName!=NULL)
      {
      sendSMS($number,"Bike $bikeNum is at stand $standName.$note");
      }
   else
      {
      sendSMS($number,"Bike $bikeNum is rented by $userName (+$phone).$note");
      }

}


function listBikes($number,$stand)
{

        global $db,$forcestack;
        $stacktopbike=FALSE;
   $userId = getUser($number);
   $stand = strtoupper($stand);

   if (!preg_match("/^[A-Z]+[0-9]*$/",$stand))
   {
      sendSMS($number,"The stand name '$stand' has not been recognized. Stands are marked by CAPITALLETTERS.");
      return;
   }

   if ($result=$db->query("SELECT standId FROM stands where standName='$stand'")) {
          if ($result->num_rows!=1)
      {
         sendSMS($number,"Stand '$stand' does not exist.");
         return;
      }
          $row =$result->fetch_assoc();
      $standId =$row["standId"];
   } else error("stand not retrieved");

   if ($forcestack)
            {
            $stacktopbike=checktopofstack($standId);
            }

   if ($result=$db->query("SELECT bikeNum FROM bikes where currentStand=$standId ORDER BY bikeNum")) {
      $rentedBikes =$result->fetch_all(MYSQLI_ASSOC);
   } else error("bikes on stand not fetched");

   if (count($rentedBikes)==0)
   {
      sendSMS($number,"Stand $stand is empty.");
      return;
   }

   $listBikes="";
   for($i=0; $i<count($rentedBikes);$i++)
   {
      if ($i!=0)
         $listBikes.=",";
      $listBikes.=$rentedBikes[$i]["bikeNum"];
      if ($stacktopbike==$rentedBikes[$i]["bikeNum"]) $listBikes.=' (first)';
   }

   $countBikes = count($rentedBikes);
   sendSMS($number,"$countBikes bike(s) on stand $stand: $listBikes");
}


function freeBikes($number)
{

        global $db;
   $userId = getUser($number);

   if ($result=$db->query("SELECT count(bikeNum) as bikeCount,placeName from bikes join stands on
   bikes.currentStand=stands.standId where stands.serviceTag=0 group by
   placeName having bikeCount>0 order by placeName")) {
      $rentedBikes =$result->fetch_all(MYSQLI_ASSOC);
   } else error("bikes on stand not fetched");

   if (count($rentedBikes)==0)
   {
      sendSMS($number,"No free bikes.");
      return;
   }

   $listBikes="";
   for($i=0; $i<count($rentedBikes);$i++)
   {
      if ($i!=0)
         $listBikes.=",";
      $listBikes.=$rentedBikes[$i]["placeName"].":".$rentedBikes[$i]["bikeCount"];
   }

   $countBikes = count($rentedBikes);
   sendSMS($number,"Free bikes counts: $listBikes");
}

function log_sms($sms_uuid, $sender, $receive_time, $sms_text, $ip)
{
   global $dbserver,$dbuser,$dbpassword,$dbname;
        $localdb=new Database($dbserver,$dbuser,$dbpassword,$dbname);
        $localdb->connect();
        $localdb->conn->autocommit(TRUE);

   $sms_uuid =$localdb->conn->real_escape_string($sms_uuid);
   $sender =$localdb->conn->real_escape_string($sender);
   $receive_time =$localdb->conn->real_escape_string($receive_time);
   $sms_text =$localdb->conn->real_escape_string($sms_text);
   $ip =$localdb->conn->real_escape_string($ip);

        $result =$localdb->query("SELECT sms_uuid FROM received WHERE sms_uuid='$sms_uuid'");
        if (DEBUG===FALSE AND $result->num_rows>=1) // sms already exists in DB, possible problem
           {
           notifyAdmins("Problem with SMS $sms_uuid!",1);
           return FALSE;
           }
        else
           {
           if ($result =$localdb->query("INSERT INTO received SET sms_uuid='$sms_uuid',sender='$sender',receive_time='$receive_time',sms_text='$sms_text',ip='$ip'"))
              {
              }
              else error("update failed");
           }

}

function note($number,$bikeNum,$message)
{

   global $db;
   $userId = getUser($number);
   $bikeNum = intval($bikeNum);

   $result=$db->query("SELECT number,userName,stands.standName FROM bikes LEFT JOIN users on bikes.currentUser=users.userID LEFT JOIN stands on bikes.currentStand=stands.standId where bikeNum=$bikeNum");
   if ($result->num_rows!=1)
      {
      sendSMS($number,"Bike $bikeNum does not exist.");
      return;
      }
   $row =$result->fetch_assoc();
   $phone=$row["number"];
   $userName=$row["userName"];
   $standName=$row["standName"];

   if ($standName!=NULL)
      {
      $bikeStatus = "B.$bikeNum is at $standName.";
      }
   else
      {
      $bikeStatus = "B.$bikeNum is rented by $userName (+$phone).";
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
      $userNote=$db->conn->real_escape_string(trim($matches[2]));
      }

   if ($userNote=="")
      {
      checkUserPrivileges($number);
      // @TODO remove SMS from deleting completely?
      $result=$db->query("UPDATE bikes SET note=NULL where bikeNum=$bikeNum");
      //only admins can delete and those will receive the confirmation in the next step.
      //sendSMS($number,"Note for bike $bikeNum deleted.");
      notifyAdmins("Note for bike $bikeNum deleted by $reportedBy.");
      }
   else
      {
      $db->query("INSERT INTO notes SET bikeNum='$bikeNum',userId='$userId',note='$userNote'");
      $noteid=$db->conn->insert_id;
      sendSMS($number,"Note for bike $bikeNum saved.");
      notifyAdmins("Note #".$noteid.": b.$bikeNum by $reportedBy ($number):".$userNote." ".$bikeStatus);
      }

}

function last($number,$bike)
{

        global $db;
   $userId = getUser($number);
   $bikeNum = intval($bike);

   if ($result=$db->query("SELECT bikeNum FROM bikes where bikeNum=$bikeNum")) {
          if ($result->num_rows!=1)
      {
         sendSMS($number,"Bike $bikeNum does not exist.");
         return;
      }
       } else error("bike not retrieved");

   if ($result=$db->query("SELECT userName,parameter,standName,action FROM `history` join users on history.userid=users.userid left join stands on stands.standid=history.parameter where bikenum=$bikeNum and action in ("RETURN","RENT","REVERT") order by time desc LIMIT 10")) {
      $bikeHistory=$result->fetch_all(MYSQLI_ASSOC);
   } else error("bike history not retrieved");

   $historyInfo="B.$bikeNum:";
   for($i=0; $i<count($bikeHistory);$i++)
   {
      if ($i!=0)
         $historyInfo.=",";

      if (($standName=$bikeHistory[$i]["standName"])!=NULL)
      {
         if ($bikeHistory[$i]["action"]=="REVERT") $historyInfo.="*";
         $historyInfo.=$standName;
      }
      else
      {
         $historyInfo.=$bikeHistory[$i]["userName"]."(".$bikeHistory[$i]["parameter"].")";
      }
   }

   sendSMS($number,$historyInfo);


}

function revert($number,$bikeNum)
{

        global $db;
        $userId = getUser($number);

        $result=$db->query("SELECT currentUser FROM bikes WHERE bikeNum=$bikeNum AND currentUser<>'NULL'");
        if (!$result->num_rows)
                {
                sendSMS($number,"Bicycle $bikeNum is not rented right now. Revert not successful!");
                return;
                }

        $result=$db->query("SELECT parameter,standName FROM stands LEFT JOIN history ON stands.standId=parameter WHERE bikeNum=$bikeNum AND action='RETURN' ORDER BY time DESC LIMIT 1");
        if ($result->num_rows==1)
                {
                        $row =$result->fetch_assoc();
                        $standId=$row["parameter"];
                        $stand=$row["standName"];
                }
        $result=$db->query("SELECT parameter FROM history WHERE bikeNum=$bikeNum AND action='RENT' ORDER BY time DESC LIMIT 1,1");
        if ($result->num_rows==1)
                {
                        $row =$result->fetch_assoc();
                        $code=$row["parameter"];
                }
        if ($standId and $code)
           {
           if ($result=$db->query("UPDATE bikes SET currentUser=NULL,currentStand=$standId,currentCode=$code where bikeNum=$bikeNum")) {
                        } else error("update failed");
           if ($result=$db->query("INSERT INTO history SET userId=$userId,bikeNum=$bikeNum,action='REVERT',parameter='$standId|$code'")) {
                        } else error("update failed");
           if ($result=$db->query("INSERT INTO history SET userId=0,bikeNum=$bikeNum,action='RENT',parameter=$code")) {
                        } else error("update failed");
           if ($result=$db->query("INSERT INTO history SET userId=0,bikeNum=$bikeNum,action='RETURN',parameter=$standId")) {
                        } else error("update failed");
           sendSMS($number,"Bicycle $bikeNum reverted to stand $stand with code $code.");
           }
        else
           {
           sendSMS($number,"No last code for bicycle $bikeNum found. Revert not successful!");
           }

}

function add($number,$email,$phone,$message)
{

        global $db;
   $userId = getUser($number);

   $phone=intval($phone);
   if ($phone<=999999999)
   {
      $phone+=421000000000;
   }

   if ($result=$db->query("SELECT number,mail,userName FROM users where number=$phone OR mail='$email'")) {
          if ($result->num_rows!=0)
      {
             $row =$result->fetch_assoc();

         $oldPhone=$row["number"];
         $oldName=$row["userName"];
         $oldMail=$row["mail"];

         sendSMS($number,"Contact information conflict: Other user already registered: $oldMail +$oldPhone $oldName");
         return;
      }
   } else error("user info not retrieved");

   if ($phone < 421000000000 || $phone > 422000000000 || !preg_match("/add\s+([a-z0-9._%+-]+@[a-z0-9.-]+)\s+\+?[0-9]+\s+(.{2,}\s.{2,})/i",$message ,$matches))
   {
      sendSMS($number,"Contact information is in incorrect format. Usage: ADD king@earth.com 0901456789 Martin Luther King Jr.");
      return;
   }
   $userName=$db->conn->real_escape_string(trim($matches[2]));
   $email=$db->conn->real_escape_string(trim($matches[1]));

   if ($result=$db->query("INSERT into users SET userName='$userName',number=$phone,mail='$email'")) {
   } else error("insert user failed");

   sendConfirmationEmail($email);

   sendSMS($number,"User $userName added. He/She has to read the email and confirm usage rules before using the system.");


}

function checkUserPrivileges($number)
{
   global $db, $sms;
   $userId=getUser($number);
   $privileges=getPrivileges($userId);
   if ($privileges==0)
      {
      sendSMS($number,"Sorry, this command is only available for the privileged users.");
      $sms->Respond();
      exit;
      }
}

?>
