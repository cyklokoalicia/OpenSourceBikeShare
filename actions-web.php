<?php
require("common.php");

function response($message,$error=0,$additional="",$log=1)
{
   global $db;
   $json=array("error"=>$error,"content"=>$message);
   if (is_array($additional))
      {
      foreach ($additional as $key=>$value)
         {
         $json[$key]=$value;
         }
      }
   $json=json_encode($json);
   if ($log==1 AND $message)
      {
      if (isset($_COOKIE["loguserid"]))
         {
         $userid=$db->conn->real_escape_string(trim($_COOKIE["loguserid"]));
         }
      else $userid=0;
      $number=getphonenumber($userid);
      logresult($number,$message);
      }
   $db->conn->commit();
   echo $json;
   exit;
}

function rent($userId,$bike,$force=FALSE)
{

   global $db,$forcestack,$watches,$credit;
   $stacktopbike=FALSE;
   $bikeNum = $bike;
   $requiredcredit=$credit["min"]+$credit["rent"]+$credit["longrental"];

   if ($force==FALSE)
      {
      $creditcheck=checkrequiredcredit($userId);
      if ($creditcheck===FALSE)
         {
         response(_('You are below required credit')." ".$requiredcredit.$credit["currency"].". "._('Please, recharge your credit.'),ERROR);
         }
      checktoomany(0,$userId);

      $result=$db->query("SELECT count(*) as countRented FROM bikes where currentUser=$userId");
      $row = $result->fetch_assoc();
      $countRented = $row["countRented"];

      $result=$db->query("SELECT userLimit FROM limits where userId=$userId");
      $row = $result->fetch_assoc();
      $limit = $row["userLimit"];

      if ($countRented>=$limit)
         {
         if ($limit==0)
            {
            response(_('You can not rent any bikes. Contact the admins to lift the ban.'),ERROR);
            }
         elseif ($limit==1)
            {
            response(_('You can only rent')." ".sprintf(ngettext('%d bike','%d bikes',$limit),$limit)." "._('at once').".",ERROR);
            }
         else
            {
            response(_('You can only rent')." ".sprintf(ngettext('%d bike','%d bikes',$limit),$limit)." "._('at once')." "._('and you have already rented')." ".$limit.".",ERROR);
            }
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
            notifyAdmins(_('Bike')." ".$bike." "._('rented out of stack by')." ".$user.". ".$stacktopbike." "._('was on the top of the stack at')." ".$stand.".",1);
            }
         if ($forcestack AND $stacktopbike<>$bike)
            {
            response(_('Bike')." ".$bike." "._('is not rentable now, you have to rent bike')." ".$stacktopbike." "._('from this stand').".",ERROR);
            }
         }
      }

   $result=$db->query("SELECT currentUser,currentCode FROM bikes WHERE bikeNum=$bikeNum");
   $row=$result->fetch_assoc();
   $currentCode=sprintf("%04d",$row["currentCode"]);
   $currentUser=$row["currentUser"];
   $result=$db->query("SELECT note FROM notes WHERE bikeNum='$bikeNum' AND deleted IS NULL ORDER BY time DESC");
   $note="";
   while ($row=$result->fetch_assoc())
      {
      $note.=$row["note"]."; ";
      }
   $note=substr($note,0,strlen($note)-2); // remove last two chars - comma and space

   $newCode=sprintf("%04d",rand(100,9900)); //do not create a code with more than one leading zero or more than two leading 9s (kind of unusual/unsafe).

   if ($force==FALSE)
      {
      if ($currentUser==$userId)
         {
         response(_('You already rented bike')." ".$bikeNum.". "._('Code is')." ".$currentCode.".",ERROR);
         return;
         }
      if ($currentUser!=0)
         {
         response(_('Bike')." ".$bikeNum." "._('is already rented').".",ERROR);
         return;
         }
      }

   $message='<h3>'._('Bike').' '.$bikeNum.': <span class="label label-primary">'._('Open with code').' '.$currentCode.'.</span></h3>'._('Change code immediately to').' <span class="label label-default">'.$newCode.'</span><br />'._('(open, rotate metal part, set new code, rotate metal part back)').'.';
   if ($note)
      {
      $message.="<br />"._('Reported issue').": <em>".$note."</em>";
      }

   $result=$db->query("UPDATE bikes SET currentUser=$userId,currentCode=$newCode,currentStand=NULL WHERE bikeNum=$bikeNum");
   if ($force==FALSE)
      {
      $result=$db->query("INSERT INTO history SET userId=$userId,bikeNum=$bikeNum,action='RENT',parameter=$newCode");
      }
   else
      {
      $result=$db->query("INSERT INTO history SET userId=$userId,bikeNum=$bikeNum,action='FORCERENT',parameter=$newCode");
      }
   response($message);

}


function returnBike($userId,$bike,$stand,$note="",$force=FALSE)
{

   global $db;
   $bikeNum = intval($bike);
   $stand = strtoupper($stand);

   if ($force==FALSE)
      {
      $result=$db->query("SELECT bikeNum FROM bikes WHERE currentUser=$userId ORDER BY bikeNum");
      $bikenumber=$result->num_rows;

      if ($bikenumber==0)
         {
         response(_('Youh currently have no rented bikes.'),ERROR);
         }
      }

   if ($force==FALSE)
      {
      $result=$db->query("SELECT currentCode FROM bikes WHERE currentUser=$userId and bikeNum=$bikeNum");
      }
   else
      {
      $result=$db->query("SELECT currentCode FROM bikes WHERE bikeNum=$bikeNum");
      }
   $row=$result->fetch_assoc();
   $currentCode = sprintf("%04d",$row["currentCode"]);

   $result=$db->query("SELECT standId FROM stands WHERE standName='$stand'");
   $row = $result->fetch_assoc();
   $standId = $row["standId"];

   $result=$db->query("UPDATE bikes SET currentUser=NULL,currentStand=$standId WHERE bikeNum=$bikeNum and currentUser=$userId");
   if ($note) addNote($userId,$bikeNum,$note);

   $message = '<h3>'._('Bike').' '.$bikeNum.': <span class="label label-primary">'._('Lock with code').' '.$currentCode.'.</span></h3>';
   $message.= '<br />'._('Please').', <strong>'._('rotate the lockpad to').' <span class="label label-default">0000</span></strong> '._('when leaving').'.';
   if ($note) $message.='<br />'._('You have also reported this problem:').' '.$note.'.';

   if ($force==FALSE)
      {
      $creditchange=changecreditendrental($bikeNum,$userId);
      if (iscreditenabled() AND $creditchange) $message.='<br />'._('Credit change').': -'.$creditchange.getcreditcurrency().'.';
      $result=$db->query("INSERT INTO history SET userId=$userId,bikeNum=$bikeNum,action='RETURN',parameter=$standId");
      }
   else
      {
      $result=$db->query("INSERT INTO history SET userId=$userId,bikeNum=$bikeNum,action='FORCERETURN',parameter=$standId");
      }
   response($message);

}


function where($userId,$bike)
{

   global $db;
   $bikeNum = $bike;

   $result=$db->query("SELECT number,userName,stands.standName FROM bikes LEFT JOIN users on bikes.currentUser=users.userID LEFT JOIN stands on bikes.currentStand=stands.standId where bikeNum=$bikeNum");
   $row = $result->fetch_assoc();
   $phone= $row["number"];
   $userName= $row["userName"];
   $standName= $row["standName"];
   $result=$db->query("SELECT note FROM notes WHERE bikeNum='$bikeNum' AND deleted IS NULL ORDER BY time DESC");
   $note="";
   while ($row=$result->fetch_assoc())
      {
      $note.=$row["note"]."; ";
      }
   $note=substr($note,0,strlen($note)-2); // remove last two chars - comma and space
   if ($note)
      {
      $note=_('Bike note:')." ".$note;
      }

   if ($standName)
      {
      response('<h3>'._('Bike').' '.$bikeNum.' '._('at').' <span class="label label-primary">'.$standName.'</span>.</h3>'.$note);
      }
   else
      {
      response('<h3>'._('Bike').' '.$bikeNum.' '._('rented by').' <span class="label label-primary">'.$userName.'</span>.</h3>'._('Phone').': <a href="tel:+'.$phone.'">+'.$phone.'</a>. '.$note);
      }

}

function addnote($userId,$bikeNum,$message)
{

   global $db;
   $userNote=$db->conn->real_escape_string(trim($message));

   $result=$db->query("SELECT userName,number from users where userId='$userId'");
   $row=$result->fetch_assoc();
   $userName=$row["userName"];
   $phone=$row["number"];
   $result=$db->query("SELECT stands.standName FROM bikes LEFT JOIN users on bikes.currentUser=users.userID LEFT JOIN stands on bikes.currentStand=stands.standId WHERE bikeNum=$bikeNum");
   $row=$result->fetch_assoc();
   $standName=$row["standName"];
   if ($standName!=NULL)
      {
      $bikeStatus=_('at')." ".$standName;
      }
      else
      {
      $bikeStatus=_('used by')." ".$userName." +".$phone;
      }
   $db->query("INSERT INTO notes SET bikeNum='$bikeNum',userId='$userId',note='$userNote'");
   notifyAdmins(_('Note')." b.".$bikeNum." (".$bikeStatus.") "._('by')." ".$userName."/".$phone.":".$userNote);

}

function listbikes($stand)
{
   global $db,$forcestack;

   $stacktopbike=FALSE;
   $stand=$db->conn->real_escape_string($stand);
   if ($forcestack)
      {
      $result=$db->query("SELECT standId FROM stands WHERE standName='$stand'");
      $row=$result->fetch_assoc();
      $stacktopbike=checktopofstack($row["standId"]);
      }
   $result=$db->query("SELECT bikeNum FROM bikes LEFT JOIN stands ON bikes.currentStand=stands.standId WHERE standName='$stand'");
   while($row=$result->fetch_assoc())
      {
      $bikenum=$row["bikeNum"];
      $result2=$db->query("SELECT note FROM notes WHERE bikeNum='$bikenum' AND deleted IS NULL ORDER BY time DESC");
      $note="";
      while ($row=$result2->fetch_assoc())
         {
         $note.=$row["note"]."; ";
         }
      $note=substr($note,0,strlen($note)-2); // remove last two chars - comma and space
      if ($note)
         {
         $bicycles[]="*".$bikenum; // bike with note / issue
         $notes[]=$note;
         }
      else
         {
         $bicycles[]=$bikenum;
         $notes[]="";
         }
      }
   if (!$result->num_rows)
      {
      $bicycles="";
      $notes="";
      }
   response($bicycles,0,array("notes"=>$notes,"stacktopbike"=>$stacktopbike),0);

}

function liststands()
{
   global $db;

   response(_('not implemented'),0,"",0); exit;
   $result=$db->query("SELECT standId,standName,standDescription,standPhoto,serviceTag,placeName,longitude,latitude FROM stands ORDER BY standName");
   while($row=$result->fetch_assoc())
      {
      $bikenum=$row["bikeNum"];
      $result2=$db->query("SELECT note FROM notes WHERE bikeNum='$bikenum' AND deleted IS NULL ORDER BY time DESC");
      $note="";
      while ($row=$result2->fetch_assoc())
         {
         $note.=$row["note"]."; ";
         }
      $note=substr($note,0,strlen($note)-2); // remove last two chars - comma and space
      if ($note)
         {
         $bicycles[]="*".$bikenum; // bike with note / issue
         $notes[]=$note;
         }
      else
         {
         $bicycles[]=$bikenum;
         $notes[]="";
         }
      }
   response($stands,0,"",0);

}

function removenote($userId,$bikeNum)
{
   global $db;

   $result=$db->query("DELETE FROM notes WHERE bikeNum=$bikeNum LIMIT XXXX");
   response(_('Note for bike')." ".$bikeNum." "._('deleted').".");
}

function last($userId,$bike=0)
{

   global $db;
   $bikeNum=intval($bike);
   if ($bikeNum)
      {
      $result=$db->query("SELECT userName,parameter,standName,action,time FROM `history` JOIN users ON history.userid=users.userid LEFT JOIN stands ON stands.standid=history.parameter WHERE bikenum=$bikeNum AND (action NOT LIKE '%CREDIT%') ORDER BY time DESC LIMIT 10");
      $historyInfo="<h3>"._('Bike')." ".$bikeNum." "._('history').":</h3><ul>";
      while($row=$result->fetch_assoc())
         {
         $time=strtotime($row["time"]);
         $historyInfo.="<li>".date("d/m H:i",$time)." - ";
         if($row["standName"]!=NULL)
            {
            $historyInfo.=$row["standName"];
            if ($row["action"]=="REVERT") $historyInfo.=' <span class="label label-warning">'._('Revert').'</span>';
            }
         else
            {
            $historyInfo.=$row["userName"].' (<span class="label label-default">'.str_pad($row["parameter"],4,"0",STR_PAD_LEFT).'</span>)';
            }
         $historyInfo.="</li>";
         }
      $historyInfo.="</ul>";
      }
   else
      {
      $result=$db->query("SELECT bikeNum FROM bikes WHERE currentUser<>''");
      $inuse=$result->num_rows;
      $result=$db->query("SELECT bikeNum,userName,standName,users.userId FROM bikes LEFT JOIN users ON bikes.currentUser=users.userId LEFT JOIN stands ON bikes.currentStand=stands.standId ORDER BY bikeNum");
      $total=$result->num_rows;
      $historyInfo="<h3>"._('Current network usage:')."</h3>";
      $historyInfo.="<h4>".sprintf(ngettext('%d bicycle','%d bicycles',$total),$total).", ".$inuse." "._('in use')."</h4><ul>";
      while($row=$result->fetch_assoc())
         {
         $historyInfo.="<li>".$row["bikeNum"]." - ";
         if($row["standName"]!=NULL)
            {
            $historyInfo.=$row["standName"];
            }
         else
            {
            $historyInfo.='<span class="bg-warning">'.$row["userName"];
            $result2=$db->query("SELECT time FROM history WHERE bikeNum=".$row["bikeNum"]." AND userId=".$row["userId"]." AND action='RENT' ORDER BY time DESC");
            $row2=$result2->fetch_assoc();
            $historyInfo.=": ".date("d/m H:i",strtotime($row2["time"])).'</span>';
            }
         $result2=$db->query("SELECT note FROM notes WHERE bikeNum='".$row["bikeNum"]."' AND deleted IS NULL ORDER BY time DESC");
         $note="";
         while ($row=$result2->fetch_assoc())
            {
            $note.=$row["note"]."; ";
            }
         $note=substr($note,0,strlen($note)-2); // remove last two chars - comma and space
         if ($note) $historyInfo.=" (".$note.")";
         $historyInfo.="</li>";
         }
      $historyInfo.="</ul>";
      }
   response($historyInfo,0,"",0);
}


function userbikes($userId)
{
   global $db;
   if (!isloggedin()) response("");
   $result=$db->query("SELECT bikeNum,currentCode FROM bikes WHERE currentUser=$userId ORDER BY bikeNum");
   while ($row=$result->fetch_assoc())
      {
      $bikenum=$row["bikeNum"];
      $bicycles[]=$bikenum;
      $codes[]=str_pad($row["currentCode"],4,"0",STR_PAD_LEFT);
      $result2=$db->query("SELECT parameter FROM history WHERE bikeNum=$bikenum AND action='RENT' ORDER BY time DESC LIMIT 1,1");
      $row=$result2->fetch_assoc();
      $oldcodes[]=str_pad($row["parameter"],4,"0",STR_PAD_LEFT);
      }
   if (!$result->num_rows) $bicycles="";
   if (!isset($codes)) $codes="";
   else $codes=array("codes"=>$codes,"oldcodes"=>$oldcodes);
   response($bicycles,0,$codes,0);
}

function revert($userId,$bikeNum)
{

   global $db;

   $standId=0;
   $result=$db->query("SELECT currentUser FROM bikes WHERE bikeNum=$bikeNum AND currentUser IS NOT NULL");
   if (!$result->num_rows)
      {
      response(_('Bicycle')." ".$bikeNum." "._('is not rented right now. Revert not successful!'),ERROR);
      return;
      }
   else
      {
      $row=$result->fetch_assoc();
      $revertusernumber=getphonenumber($row["currentUser"]);
      }
   $result=$db->query("SELECT parameter,standName FROM stands LEFT JOIN history ON stands.standId=parameter WHERE bikeNum=$bikeNum AND action='RETURN' ORDER BY time DESC LIMIT 1");
   if ($result->num_rows==1)
      {
      $row = $result->fetch_assoc();
      $standId=$row["parameter"];
      $stand=$row["standName"];
      }
   $result=$db->query("SELECT parameter FROM history WHERE bikeNum=$bikeNum AND action='RENT' ORDER BY time DESC LIMIT 1,1");
   if ($result->num_rows==1)
      {
      $row = $result->fetch_assoc();
      $code=str_pad($row["parameter"],4,"0",STR_PAD_LEFT);
      }
   if ($standId and $code)
      {
      $result=$db->query("UPDATE bikes SET currentUser=NULL,currentStand=$standId,currentCode=$code where bikeNum=$bikeNum");
      $result=$db->query("INSERT INTO history SET userId=$userId,bikeNum=$bikeNum,action='REVERT',parameter='$standId|$code'");
      $result=$db->query("INSERT INTO history SET userId=0,bikeNum=$bikeNum,action='RENT',parameter=$code");
      $result=$db->query("INSERT INTO history SET userId=0,bikeNum=$bikeNum,action='RETURN',parameter=$standId");
      response('<h3>'._('Bicycle').' '.$bikeNum.' '._('reverted to').' <span class="label label-primary">'.$stand.'</span> '._('with code').' <span class="label label-primary">'.$code.'</span>.</h3>');
      sendSMS($revertusernumber,_('Bike')." ".$bikeNum." "._('has been returned. You can now rent a new bicycle.'));
      }
   else
      {
      response(_('No last stand or code for bicycle')." ".$bikeNum." "._('found. Revert not successful!'),ERROR);
      }

}

function register($number,$code,$checkcode,$fullname,$email,$password,$password2,$existing)
{
   global $db, $dbpassword, $countrycode, $systemURL;

   $number=$db->conn->real_escape_string(trim($number));
   $code=$db->conn->real_escape_string(trim($code));
   $checkcode=$db->conn->real_escape_string(trim($checkcode));
   $fullname=$db->conn->real_escape_string(trim($fullname));
   $email=$db->conn->real_escape_string(trim($email));
   $password=$db->conn->real_escape_string(trim($password));
   $password2=$db->conn->real_escape_string(trim($password2));
   $existing=$db->conn->real_escape_string(trim($existing));
   $parametercheck=$number.";".str_replace(" ","",$code).";".$checkcode;
   if ($password<>$password2)
      {
      response(_('Password do not match. Please correct and try again.'),ERROR);
      }
   if (issmssystemenabled()==TRUE)
      {
      $result=$db->query("SELECT parameter FROM history WHERE userId=0 AND bikeNum=0 AND action='REGISTER' AND parameter='$parametercheck' ORDER BY time DESC LIMIT 1");
      if ($result->num_rows==1)
         {
         if (!$existing) // new user registration
            {
            $result=$db->query("INSERT INTO users SET userName='$fullname',password=SHA2('$password',512),mail='$email',number='$number',privileges=0");
            $userId=$db->conn->insert_id;
            sendConfirmationEmail($email);
            response(_('You have been successfully registered. Please, check your email and read the instructions to finish your registration.'));
            }
         else // existing user, password change
            {
            $result=$db->query("SELECT userId FROM users WHERE number='$number'");
            $row=$result->fetch_assoc();
            $userId=$row["userId"];
            $result=$db->query("UPDATE users SET password=SHA2('$password',512) WHERE userId='$userId'");
            response(_('Password successfully changed. Your username is your phone number. Continue to').' <a href="'.$systemURL.'">'._('login').'</a>.');
            }
         }
      else
         {
         response(_('Problem with the SMS code entered. Please check and try again.'),ERROR);
         }
      }
   else // SMS system disabled
      {
      $result=$db->query("INSERT INTO users SET userName='$fullname',password=SHA2('$password',512),mail='$email',number='',privileges=0");
      $userId=$db->conn->insert_id;
      $result=$db->query("UPDATE users SET number='$userId' WHERE userId='$userId'");
      sendConfirmationEmail($email);
      response(_('You have been successfully registered. Please, check your email and read the instructions to finish your registration. Your number for login is:')." ".$userId);
      }

}

function login($number,$password)
{
   global $db,$systemURL,$countrycode;

   $number=$db->conn->real_escape_string(trim($number));
   $password=$db->conn->real_escape_string(trim($password));
   $number=str_replace(" ","",$number); $number=str_replace("-","",$number); $number=str_replace("/","",$number);
   if ($number[0]=="0") $number=$countrycode.substr($number,1,strlen($number));

   $result=$db->query("SELECT userId FROM users WHERE number='$number' AND password=SHA2('$password',512)");
   if ($result->num_rows==1)
      {
      $row=$result->fetch_assoc();
      $userId=$row["userId"];
      $sessionId=hash('sha256',$userId.$number.time());
      $timeStamp=time()+86400*14; // 14 days to keep user logged in
      $result=$db->query("DELETE FROM sessions WHERE userId='$userId'");
      $result=$db->query("INSERT INTO sessions SET userId='$userId',sessionId='$sessionId',timeStamp='$timeStamp'");
      $db->conn->commit();
      setcookie("loguserid",$userId,time()+86400*14);
      setcookie("logsession",$sessionId,time()+86400*14);
      header("HTTP/1.1 301 Moved permanently");
      header("Location: ".$systemURL);
      header("Connection: close");
      exit;
      }
   else
      {
      header("HTTP/1.1 301 Moved permanently");
      header("Location: ".$systemURL."?error=1");
      header("Connection: close");
      exit;
      }

}

function logout()
{
   global $db,$systemURL;
   if (isset($_COOKIE["loguserid"]) AND isset($_COOKIE["logsession"]))
      {
      $userid=$db->conn->real_escape_string(trim($_COOKIE["loguserid"]));
      $session=$db->conn->real_escape_string(trim($_COOKIE["logsession"]));
      $result=$db->query("DELETE FROM sessions WHERE userId='$userid'");
      $db->conn->commit();
      }
   header("HTTP/1.1 301 Moved permanently");
   header("Location: ".$systemURL);
   header("Connection: close");
   exit;
}

function checkprivileges($userid)
{
   global $db;
   $privileges=getprivileges($userid);
   if ($privileges<1)
      {
      response(_('Sorry, this command is only available for the privileged users.'),ERROR);
      exit;
      }
}

function smscode($number)
{

   global $db, $gatewayId, $gatewayKey, $gatewaySenderNumber, $countrycode, $connectors;
   srand();

   $number=str_replace(" ","",$number); $number=str_replace("-","",$number); $number=str_replace("/","",$number);
   $number=$countrycode.substr($number,1,strlen($number));
   $number = $db->conn->real_escape_string($number);
   $userexists=0;
   $result=$db->query("SELECT userId FROM users WHERE number='$number'");
   if ($result->num_rows) $userexists=1;

   $smscode=chr(rand(65,90)).chr(rand(65,90))." ".rand(100000,999999);
   $smscodenormalized=str_replace(" ","",$smscode);
   $checkcode=md5("WB".$number.$smscodenormalized);
   if (!$userexists) $text=_('Enter this code to register:')." ".$smscode;
   else $text=_('Enter this code to change password:')." ".$smscode;
   $text=$db->conn->real_escape_string($text);

   $result=$db->query("INSERT INTO sent SET number='$number',text='$text'");
   $result=$db->query("INSERT INTO history SET userId=0,bikeNum=0,action='REGISTER',parameter='$number;$smscodenormalized;$checkcode'");

   if (DEBUG===TRUE)
      {
      response($number,0,array("checkcode"=>$checkcode,"smscode"=>$smscode,"existing"=>$userexists));
      }
   else
      {
      sendSMS($number,$text);
      if (issmssystemenabled()==TRUE) response($number,0,array("checkcode"=>$checkcode,"existing"=>$userexists));
      else response($number,0,array("checkcode"=>$checkcode,"existing"=>$userexists));
      }
}

function trips($userId,$bike=0)
{

   global $db;
   $bikeNum=intval($bike);
   if ($bikeNum)
      {
      $result=$db->query("SELECT longitude,latitude FROM `history` LEFT JOIN stands ON stands.standid=history.parameter WHERE bikenum=$bikeNum AND action='RETURN' ORDER BY time DESC");
      while($row = $result->fetch_assoc())
         {
         $jsoncontent[]=array("longitude"=>$row["longitude"],"latitude"=>$row["latitude"]);
         }
      }
   else
      {
      $result=$db->query("SELECT bikeNum,longitude,latitude FROM `history` LEFT JOIN stands ON stands.standid=history.parameter WHERE action='RETURN' ORDER BY bikeNum,time DESC");
      $i=0;
      while($row = $result->fetch_assoc())
         {
         $bikenum=$row["bikeNum"];
         $jsoncontent[$bikenum][]=array("longitude"=>$row["longitude"],"latitude"=>$row["latitude"]);
         }
      }
   echo json_encode($jsoncontent); // TODO change to response function
}

function getuserlist()
{
   global $db;
   $result=$db->query("SELECT users.userId,username,mail,number,privileges,credit,userLimit FROM users LEFT JOIN credit ON users.userId=credit.userId LEFT JOIN limits ON users.userId=limits.userId ORDER BY username");
   while($row = $result->fetch_assoc())
      {
      $jsoncontent[]=array("userid"=>$row["userId"],"username"=>$row["username"],"mail"=>$row["mail"],"number"=>$row["number"],"privileges"=>$row["privileges"],"credit"=>$row["credit"],"limit"=>$row["userLimit"]);
      }
   echo json_encode($jsoncontent);// TODO change to response function
}

function getuserstats()
{
   global $db;
   $result=$db->query("SELECT users.userId,username,count(action) AS count FROM users LEFT JOIN history ON users.userId=history.userId WHERE history.userId IS NOT NULL GROUP BY username ORDER BY count DESC");
   while($row = $result->fetch_assoc())
      {
      $result2=$db->query("SELECT count(action) AS rentals FROM history WHERE action='RENT' AND userId=".$row["userId"]);
      $row2=$result2->fetch_assoc();
      $result2=$db->query("SELECT count(action) AS returns FROM history WHERE action='RETURN' AND userId=".$row["userId"]);
      $row3=$result2->fetch_assoc();
      $jsoncontent[]=array("userid"=>$row["userId"],"username"=>$row["username"],"count"=>$row["count"],"rentals"=>$row2["rentals"],"returns"=>$row3["returns"]);
      }
   echo json_encode($jsoncontent);// TODO change to response function
}

function edituser($userid)
{
   global $db;
   $result=$db->query("SELECT users.userId,userName,mail,number,privileges,userLimit,credit FROM users LEFT JOIN limits ON users.userId=limits.userId LEFT JOIN credit ON users.userId=credit.userId WHERE users.userId=".$userid);
   $row=$result->fetch_assoc();
   $jsoncontent=array("userid"=>$row["userId"],"username"=>$row["userName"],"email"=>$row["mail"],"phone"=>$row["number"],"privileges"=>$row["privileges"],"limit"=>$row["userLimit"],"credit"=>$row["credit"]);
   echo json_encode($jsoncontent);// TODO change to response function
}

function saveuser($userid,$username,$email,$phone,$privileges,$limit)
{
   global $db;
   $result=$db->query("UPDATE users SET username='$username',mail='$email',privileges='$privileges' WHERE userId=".$userid);
   if ($phone) $result=$db->query("UPDATE users SET number='$phone' WHERE userId=".$userid);
   $result=$db->query("UPDATE limits SET userLimit='$limit' WHERE userId=".$userid);
   response(_('Details of user')." ".$username." "._('updated').".");
}

function addcredit($userid,$creditmultiplier)
{
   global $db, $credit;
   $requiredcredit=$credit["min"]+$credit["rent"]+$credit["longrental"];
   $addcreditamount=$requiredcredit*$creditmultiplier;
   $result=$db->query("UPDATE credit SET credit=credit+".$addcreditamount." WHERE userId=".$userid);
   $result=$db->query("INSERT INTO history SET userId=$userid,action='CREDITCHANGE',parameter='".$addcreditamount."|add+".$addcreditamount."'");
   $result=$db->query("SELECT userName FROM users WHERE users.userId=".$userid);
   $row=$result->fetch_assoc();
   response(_('Added')." ".$addcreditamount.$credit["currency"]." "._('credit for')." ".$row["userName"].".");
}

function getcouponlist()
{
   global $db, $credit;
   if (iscreditenabled()==FALSE) return; // if credit system disabled, exit
   $result=$db->query("SELECT coupon,value FROM coupons WHERE status='0' ORDER BY status,value,coupon");
   while($row=$result->fetch_assoc())
      {
      $jsoncontent[]=array("coupon"=>$row["coupon"],"value"=>$row["value"]);
      }
   echo json_encode($jsoncontent);// TODO change to response function
}

function generatecoupons($multiplier)
{
   global $db, $credit;
   if (iscreditenabled()==FALSE) return; // if credit system disabled, exit
   $requiredcredit=$credit["min"]+$credit["rent"]+$credit["longrental"];
   $value=$requiredcredit*$multiplier;
   $codes=generatecodes(10,6);
   foreach ($codes as $code)
      {
      $result=$db->query("INSERT IGNORE INTO coupons SET coupon='".$code."',value='".$value."',status='0'");
      }
   response(_('Generated 10 new').' '.$value.' '.$credit["currency"].' '._('coupons').'.',0,array("coupons"=>$codes));
}

function sellcoupon($coupon)
{
   global $db, $credit;
   if (iscreditenabled()==FALSE) return; // if credit system disabled, exit
   $result=$db->query("UPDATE coupons SET status='1' WHERE coupon='".$coupon."'");
   response(_('Coupon').' '.$coupon.' '._('sold').'.');
}

function validatecoupon($userid,$coupon)
{
   global $db, $credit;
   if (iscreditenabled()==FALSE) return; // if credit system disabled, exit
   $result=$db->query("SELECT coupon,value FROM coupons WHERE coupon='".$coupon."' AND status<'2'");
   if ($result->num_rows==1)
      {
      $row=$result->fetch_assoc();
      $value=$row["value"];
      $result=$db->query("UPDATE credit SET credit=credit+'".$value."' WHERE userId='".$userid."'");
      $result=$db->query("INSERT INTO history SET userId=$userid,action='CREDITCHANGE',parameter='".$value."|add+".$value."|".$coupon."'");
      $result=$db->query("UPDATE coupons SET status='2' WHERE coupon='".$coupon."'");
      response('+'.$value.' '.$credit["currency"].'. '._('Coupon').' '.$coupon.' '._('has been redeemed').'.');
      }
   response(_('Invalid coupon, try again.'),1);
}

function resetpassword($number)
{
   global $db, $systemname, $systemrules, $systemURL;

   $result=$db->query("SELECT mail,userName FROM users WHERE number='$number'");
   if (!$result->num_rows) response(_('No such user found.'),1);
   $row=$result->fetch_assoc();
   $email=$row["mail"];
   $username=$row["userName"];

   $subject = _('Password reset');

   mt_srand(crc32(microtime()));
   $password=substr(md5(mt_rand().microtime().$email),0,8);

   $result=$db->query("UPDATE users SET password=SHA2('$password',512) WHERE number='".$number."'");

   $names=preg_split("/[\s,]+/",$username);
   $firstname=$names[0];
   $message=_('Hello').' '.$firstname.",\n\n".
   _('Your password has been reset successfully.')."\n\n".
   _('Your new password is:')."\n".$password;

   sendEmail($email, $subject, $message);
   response(_('Your password has been reset successfully.').' '._('Check your email.'));
}

function mapgetmarkers()
{
   global $db;

   $jsoncontent=array();
   $result=$db->query("SELECT standId,count(bikeNum) AS bikecount,standDescription,standName,standPhoto,longitude AS lon, latitude AS lat FROM stands LEFT JOIN bikes on bikes.currentStand=stands.standId WHERE stands.serviceTag=0 GROUP BY standName ORDER BY standName");
   while($row = $result->fetch_assoc())
      {
      $jsoncontent[]=$row;
      }
   echo json_encode($jsoncontent); // TODO proper response function
}

function mapgetlimit($userId)
{
   global $db;

   if (!isloggedin()) response("");
   $result=$db->query("SELECT count(*) as countRented FROM bikes where currentUser=$userId");
   $row = $result->fetch_assoc();
   $rented= $row["countRented"];

   $result=$db->query("SELECT userLimit FROM limits where userId=$userId");
   $row = $result->fetch_assoc();
   $limit = $row["userLimit"];

   $currentlimit=$limit-$rented;

   $usercredit=0;
   $usercredit=getusercredit($userId);

   echo json_encode(array("limit"=>$currentlimit,"rented"=>$rented,"usercredit"=>$usercredit));
}

function mapgeolocation ($userid,$lat,$long)
{
   global $db;

   $result=$db->query("INSERT INTO geolocation SET userId='$userid',latitude='$lat',longitude='$long'");

   response("");

}

// TODO for admins: show bikes position on map depending on the user (allowed) geolocation, do not display user bikes without geoloc

?>