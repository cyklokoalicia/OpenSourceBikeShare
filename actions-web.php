<?php
require("common.php");
require("base.php");

function response($message,$error=0,$additional="",$log=1)
{
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
         $userid=$_COOKIE["loguserid"];
         }
      else $userid=0;
      $number=getphonenumber($userid);
      logresult($number,$message);
      }
   R::commit();
   echo $json;
   exit;
}

function status($action,$result,$values=FALSE)
{
if ($action=='LISTBIKES')
   {
   if ($result==OK)
      {
      response('',0,array("bicycles"=>$values)); //array("notes"=>$values->notes,"stacktopbike"=>$values->stacktopbike)
      }
   elseif ($result==100)
      {
      response('');
      }
   }
elseif ($action=='RENT')
   {
   if ($result==OK)
      {
      $message='<h3>'._('Bike').' '.$values->bikenum.': <span class="label label-primary">'._('Open with code').' '.$values->currentcode.'.</span></h3>'._('Change code immediately to').' <span class="label label-default">'.$values->newcode.'</span><br />'._('(open, rotate metal part, set new code, rotate metal part back)').'.';
      if (isset($values->note)) $message.="<br />"._('Reported issue:')." <em>".$values->note."</em>";
      response($message);
      }
   elseif ($result==100)
      {
      response(_('You can not rent any bikes. Contact the admins to lift the ban.'),ERROR);
      }
   elseif ($result==101)
      {
      response(_('You can only rent')." ".sprintf(ngettext('%d bike','%d bikes',$values->userlimit),$values->userlimit)." "._('at once').".",ERROR);
      }
   elseif ($result==102)
      {
      response(_('You can only rent')." ".sprintf(ngettext('%d bike','%d bikes',$values->userlimit),$values->userlimit)." "._('at once and you have already rented')." ".$values->userlimit.".",ERROR);
      }
   elseif ($result==110)
      {
      response(_('Bike')." ".$values->bikenum." "._('is no$user->t rentable now, you have to rent bike')." ".$values->stacktopbike." "._('from this stand').".",ERROR);
      }
   elseif ($result==120)
      {
      response(_('You have already rented the bike')." ".$values->bikenum.". "._('Code is')." ".$values->currentcode.".",ERROR);
      }
   elseif ($result==121)
      {
      response(_('Bike')." ".$values->bikenum." "._('is already rented by someone else').".",ERROR);
      }
   elseif ($result==130)
      {
      response(_('You are below required credit')." ".getrequiredcredit().getcreditcurrency().". "._('Please, recharge your credit.'),ERROR);
      }
   }
elseif ($action=='RETURN')
   {
   if ($result==OK)
      {
      $message='<h3>'._('Bike').' '.$values->bikenum.': <span class="label label-primary">'._('Lock with code').' '.$values->currentcode.'.</span></h3>';
      $message.='<br />'._('Please').', <strong>'._('rotate the lockpad to').' <span class="label label-default">0000</span></strong> '._('when leaving').'.';
      if (iscreditenabled() AND isset($values->creditchange)) $message.='<br />'._('Credit change').': -'.$values->creditchange.getcreditcurrency().'.';
      if (isset($values->note)) $message.='<br />'._('You have also reported this problem:').' '.$values->note.'.';
      response($message);
      }
   elseif ($result==100)
      {
      response(_('You have no rented bikes currently.'),ERROR);
      }
   elseif ($result==102)
      {
         $message=_('You do not have the bike')." ".$values->bikenum." rented.";
      if (isset($values->bikelist)) $message.=" "._('You have rented the following')." ".sprintf(ngettext('%d bike','%d bikes',$values->countrented),$values->countrented).": ".$values->bikelist.".";
      response($message,ERROR);
      }
   }
elseif ($action=='CHECKBIKE')
   {
   if ($result==100)
      {
      response('<h3>Bike '.$bikenum.' does not exist!</h3>',ERROR);
      }
   }
elseif ($action=='CHECKSTAND')
   {
   if ($result==100)
      {
      response('<h3>'._('Stand').' '.$standname.' '._('does not exist').'!</h3>',ERROR);
      }
   }
elseif ($action=='WHERE')
   {
   if ($result==100)
      {
      $message='<h3>'._('Bike').' '.$values->bikenum.' '._('at').' <span class="label label-primary">'.$values->standname.'</span>.</h3>';
      if (isset($values->note)) $message.=' ('._('Reported issue:')." ".$values->note.')';
      response($message);
      }
   elseif ($result==101)
      {
      $message='<h3>'._('Bike').' '.$values->bikenum.' '._('rented by').' <span class="label label-primary">'.$values->username.'</span>.</h3>'._('Phone').': <a href="tel:+'.$values->phone.'">+'.$values->phone.'</a>.';
      if (isset($values->note)) $message.=' ('._('Reported issue:')." ".$values->note.')';
      response($message);
      }
   }
elseif ($action=='DELNOTE')
   {
   if ($result==OK)
      {
      response('<h3>'._('Note for bike')." ".$values->bikenum." "._('deleted').'.</h3>');
      }
   }

response('Unhandled status '.$result.' in '.$action.' in file '.__FILE__.'.',ERROR);

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
            if (strpos($row["parameter"],"|"))
               {
               $revertcode=explode("|",$row["parameter"]);
               $revertcode=$revertcode[1];
               }
            if ($row["action"]=="REVERT") $historyInfo.=' <span class="label label-warning">'._('Revert').' ('.str_pad($revertcode,4,"0",STR_PAD_LEFT).')</span>';
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
   $result=$db->query("SELECT parameter,standName FROM stands LEFT JOIN history ON stands.standId=parameter WHERE bikeNum=$bikeNum AND action IN ('RETURN','FORCERETURN') ORDER BY time DESC LIMIT 1");
   if ($result->num_rows==1)
      {
      $row = $result->fetch_assoc();
      $standId=$row["parameter"];
      $stand=$row["standName"];
      }
   $result=$db->query("SELECT parameter FROM history WHERE bikeNum=$bikeNum AND action IN ('RENT','FORCERENT') ORDER BY time DESC LIMIT 1,1");
   if ($result->num_rows==1)
      {
      $row = $result->fetch_assoc();
      $code=str_pad($row["parameter"],4,"0",STR_PAD_LEFT);
      }
   if ($standId and $code)
      {
      $result=$db->query("UPDATE bikes SET currentUser=NULL,currentStand=$standId,currentCode=$code WHERE bikeNum=$bikeNum");
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
   global $systemURL,$countrycode;

   $number=normalizephonenumber($number);

   $user=R::findOne('users','(number=:number) AND password=SHA2(:password,512)',[':number'=>$number,':password'=>$password]);
   if (!empty($user))
      {
      $timestamp=time()+86400*14; // 14 days to keep user logged in
      $session=R::findOne('sessions','userid=?',[$user->id]);
      if (!empty($session))
         {
         R::trash($session);
         }
      $session=R::dispense('sessions');
      $session->userid=$user->id;
      $session->sessionid=hash('sha256',$user->id.$user->number.time());
      $session->timestamp=time()+86400*14;
      R::store($session);
      R::commit();
      setcookie("loguserid",$user->id,time()+86400*14);
      setcookie("logsession",$session->sessionid,time()+86400*14);
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
   global $systemURL;
   if (isset($_COOKIE["loguserid"]) AND isset($_COOKIE["logsession"]))
      {
      $session=R::findOne('sessions','userid=?',[$_COOKIE["loguserid"]]);
      if (!empty($session))
         {
         R::trash($session);
         R::commit();
         }
      }
   header("HTTP/1.1 301 Moved permanently");
   header("Location: ".$systemURL);
   header("Connection: close");
   exit;
}



function smscode($number)
{

   global $db, $gatewayId, $gatewayKey, $gatewaySenderNumber, $connectors;
   srand();

   $number=normalizephonenumber($number);
   $number=$db->conn->real_escape_string($number);
   $userexists=0;
   $result=$db->query("SELECT userId FROM users WHERE number='$number'");
   if ($result->num_rows) $userexists=1;

   $smscode=chr(rand(65,90)).chr(rand(65,90))." ".rand(100000,999999);
   $smscodenormalized=str_replace(" ","",$smscode);
   $checkcode=md5("WB".$number.$smscodenormalized);
   if (!$userexists) $text=_('Enter this code to register:')." ".$smscode;
   else $text=_('Enter this code to change password:')." ".$smscode;
   $text=$db->conn->real_escape_string($text);

   if (!issmssystemenabled()) $result=$db->query("INSERT INTO sent SET number='$number',text='$text'");
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

function getusagestats()
{
   global $db;
   $result=$db->query("SELECT count(action) AS count,DATE(time) AS day,action FROM history WHERE userId IS NOT NULL AND action IN ('RENT','RETURN') GROUP BY day,action ORDER BY day DESC LIMIT 60");
   while($row=$result->fetch_assoc())
      {
      $jsoncontent[]=array("day"=>$row["day"],"count"=>$row["count"],"action"=>$row["action"]);
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







// TODO for admins: show bikes position on map depending on the user (allowed) geolocation, do not display user bikes without geoloc

?>
