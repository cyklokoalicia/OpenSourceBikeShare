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
         response("You are below required credit ".$requiredcredit.$credit["currency"].". Please, recharge your credit.",ERROR);
         }
      checktoomany(0,$userId);

      $result = $db->query("SELECT count(*) as countRented FROM bikes where currentUser=$userId");
      $row = $result->fetch_assoc();
      $countRented = $row["countRented"];

      $result = $db->query("SELECT userLimit FROM limits where userId=$userId");
      $row = $result->fetch_assoc();
      $limit = $row["userLimit"];

      if ($countRented>=$limit)
         {
         if ($limit==0)
            {
            response("You can not rent any bikes. Contact the admins to lift the ban.",ERROR);
            }
         elseif ($limit==1)
            {
            response("You can only rent ".$limit." bike at once.",ERROR);
            }
         else
            {
            response("You can only rent ".$limit." bikes at once and you have already rented ".$limit.".",ERROR);
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
            notifyAdmins("Bike ".$bike." rented out of stack by ".$user.". ".$stacktopbike." was on the top of the stack at ".$stand.".",1);
            }
         if ($forcestack AND $stacktopbike<>$bike)
            {
            response("Bike ".$bike." is not rentable now, you have to rent bike ".$stacktopbike." from this stand.",ERROR);
            }
         }
      }

   $result=$db->query("SELECT currentUser,currentCode,note FROM bikes where bikeNum=$bikeNum");
   $row=$result->fetch_assoc();
   $currentCode=sprintf("%04d",$row["currentCode"]);
   $currentUser=$row["currentUser"];
   $note=$row["note"];

   $newCode=sprintf("%04d",rand(100,9900)); //do not create a code with more than one leading zero or more than two leading 9s (kind of unusual/unsafe).

   if ($force==FALSE)
      {
      if ($currentUser==$userId)
         {
         response("You already rented the bike $bikeNum. Code is $currentCode. Return the bike with command: RETURN bikenumber standname.",ERROR);
         return;
         }
      if ($currentUser!=0)
         {
         response("The bike $bikeNum is already rented.",ERROR);
         return;
         }
      }

   $message='<h3>Bike '.$bikeNum.': <span class="label label-primary">Open with code '.$currentCode.'.</span></h3>Change code immediately to <span class="label label-default">'.$newCode.'</span><br />(open, rotate metal part, set new code, rotate metal part back).';
   if ($note)
      {
      $message.="<br />Reported issue: <em>".$note."</em>";
      }

   $result = $db->query("UPDATE bikes SET currentUser=$userId,currentCode=$newCode,currentStand=NULL WHERE bikeNum=$bikeNum");
   if ($force==FALSE)
      {
      $result = $db->query("INSERT INTO history SET userId=$userId,bikeNum=$bikeNum,action='RENT',parameter=$newCode");
      }
   else
      {
      $result = $db->query("INSERT INTO history SET userId=$userId,bikeNum=$bikeNum,action='FORCERENT',parameter=$newCode");
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
      $result = $db->query("SELECT bikeNum FROM bikes WHERE currentUser=$userId ORDER BY bikeNum");
      $rentedBikes = $result->fetch_all(MYSQLI_ASSOC);

      if (count($rentedBikes)==0)
         {
         response("You have no rented bikes currently.",ERROR);
         }
      }

   if ($force==FALSE)
      {
      $result = $db->query("SELECT currentCode FROM bikes WHERE currentUser=$userId and bikeNum=$bikeNum");
      }
   else
      {
      $result = $db->query("SELECT currentCode FROM bikes WHERE bikeNum=$bikeNum");
      }
   $row = $result->fetch_assoc();
   $currentCode = sprintf("%04d",$row["currentCode"]);

   $result = $db->query("SELECT standId FROM stands where standName='$stand'");
   $row = $result->fetch_assoc();
   $standId = $row["standId"];

   $result = $db->query("UPDATE bikes SET currentUser=NULL,currentStand=$standId WHERE bikeNum=$bikeNum and currentUser=$userId");
   if ($note) addNote($userId,$bikeNum,$note);

   $message = '<h3>Bike '.$bikeNum.': <span class="label label-primary">Lock with code '.$currentCode.'.</span></h3>';
   $message.= '<br />Please, <strong>rotate the lockpad to <span class="label label-default">0000</span></strong> when leaving.';
   if ($note) $message.='<br />You have also reported this problem: '.$note.'.';

   if ($force==FALSE)
      {
      $creditchange=changecreditendrental($bikeNum,$userId);
      if (iscreditenabled() AND $creditchange) $message.='<br />Credit change: -'.$creditchange.getcreditcurrency().'.';
      $result = $db->query("INSERT INTO history SET userId=$userId,bikeNum=$bikeNum,action='RETURN',parameter=$standId");
      }
   else
      {
      $result = $db->query("INSERT INTO history SET userId=$userId,bikeNum=$bikeNum,action='FORCERETURN',parameter=$standId");
      }
   response($message);

}


function where($userId,$bike)
{

   global $db;
   $bikeNum = $bike;

   $result = $db->query("SELECT number,userName,stands.standName,note FROM bikes LEFT JOIN users on bikes.currentUser=users.userID LEFT JOIN stands on bikes.currentStand=stands.standId where bikeNum=$bikeNum");
   $row = $result->fetch_assoc();
   $phone= $row["number"];
   $userName= $row["userName"];
   $standName= $row["standName"];
   $note= $row["note"];
   if ($note!="")
      {
      $note="Bike note: $note";
      }

   if ($standName)
      {
      response('<h3>Bike '.$bikeNum.' at <span class="label label-primary">'.$standName.'</span>.</h3>.'.$note);
      }
   else
      {
      response('<h3>Bike '.$bikeNum.' rented by <span class="label label-primary">'.$userName.'</span>.</h3>Phone: <a href="tel:+'.$phone.'">+'.$phone.'</a>. '.$note);
      }

}

function checkbikeno($bikeNum)
{
   global $db;
   $result = $db->query("SELECT bikeNum FROM bikes WHERE bikeNum=$bikeNum");
   if (!$result->num_rows)
      {
      response('<h3>Bike '.$bikeNum.' does not exist!</h3>',ERROR);
      }
}

function checkstandname($stand)
{
   global $db;
   $standname=trim(strtoupper($stand));
   $result = $db->query("SELECT standName FROM stands WHERE standName='$stand'");
   if (!$result->num_rows)
      {
      response('<h3>Stand '.$stand.' does not exist!</h3>',ERROR);
      }
}

function logrequest($userid)
{
   global $dbServer,$dbUser,$dbPassword,$dbName;
   $localdb=new Database($dbServer,$dbUser,$dbPassword,$dbName);
   $localdb->connect();
   $localdb->conn->autocommit(TRUE);

   $number=getphonenumber($userid);

   $result = $localdb->query("INSERT INTO received SET sender='$number',receive_time='".date("Y-m-d H:i:s")."',sms_text='".$_SERVER['REQUEST_URI']."',ip='".$_SERVER['REMOTE_ADDR']."'");

}

function logresult($userid,$text)
{
   global $dbServer,$dbUser,$dbPassword,$dbName;

   $localdb=new Database($dbServer,$dbUser,$dbPassword,$dbName);
   $localdb->connect();
   $localdb->conn->autocommit(TRUE);
   $userid = $localdb->conn->real_escape_string($userid);
   $logtext="";
   if (is_array($text))
      {
      foreach ($text as $value)
         {
         $logtext.=$value.", ";
         }
      }
   else
      {
      $logtext=$text;
      }

   $logtext = strip_tags($localdb->conn->real_escape_string($logtext));

   $result = $localdb->query("INSERT INTO sent SET number='$userid',text='$logtext'");

}

function addnote($userId,$bikeNum,$message)
{

   global $db;
   $userNote=$db->conn->real_escape_string(trim($message));

   $result=$db->query("SELECT stands.standName FROM bikes LEFT JOIN users on bikes.currentUser=users.userID LEFT JOIN stands on bikes.currentStand=stands.standId where bikeNum=$bikeNum");
   $row=$result->fetch_assoc();
   $standName=$row["standName"];
   if ($standName!=NULL)
      {
      $bikeStatus="at $standName";
      }
      else
      {
      $bikeStatus="rented by $userName +$phone";
      }
   $result=$db->query("SELECT userName,number from users where userId=$userId");
   $row=$result->fetch_assoc();
   $userName=$row["userName"];
   $phone=$row["number"];
   $db->query("UPDATE bikes SET note='$userNote' where bikeNum=$bikeNum");
   notifyAdmins("Note b.$bikeNum (".$bikeStatus.") by $userName/$phone:".$userNote);

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
   $result=$db->query("SELECT bikeNum,note FROM bikes LEFT JOIN stands ON bikes.currentStand=stands.standId WHERE standName='$stand'");
   while($row=$result->fetch_assoc())
      {
      $bikenum=$row["bikeNum"];
      if ($row["note"])
         {
         $bicycles[]="*".$bikenum; // bike with note / issue
         $notes[]=$row["note"];
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

function removenote($userId,$bikeNum)
{
   global $db;

   $result = $db->query("UPDATE bikes SET note=NULL where bikeNum=$bikeNum");
   response("Note for bike $bikeNum deleted.");
}

function last($userId,$bike)
{

   global $db;
   $bikeNum=intval($bike);
   if ($bikeNum)
      {
      $result=$db->query("SELECT userName,parameter,standName,action,time FROM `history` JOIN users ON history.userid=users.userid LEFT JOIN stands ON stands.standid=history.parameter WHERE bikenum=$bikeNum ORDER BY time DESC LIMIT 10");
      $bikeHistory=$result->fetch_all(MYSQLI_ASSOC);
      $historyInfo="<h3>Bike $bikeNum history:</h3><ul>";
      for($i=0; $i<count($bikeHistory);$i++)
         {
         $time=strtotime($bikeHistory[$i]["time"]);
         $historyInfo.="<li>".date("d/m H:i",$time)." - ";
         if($bikeHistory[$i]["standName"]!=NULL)
            {
            $historyInfo.=$bikeHistory[$i]["standName"];
            if ($bikeHistory[$i]["action"]=="REVERT") $historyInfo.=' <span class="label label-warning">Revert</span>';
            }
         else
            {
            $historyInfo.=$bikeHistory[$i]["userName"].' (<span class="label label-default">'.str_pad($bikeHistory[$i]["parameter"],4,"0",STR_PAD_LEFT).'</span>)';
            }
         $historyInfo.="</li>";
         }
      $historyInfo.="</ul>";
      }
   else
      {
      $result=$db->query("SELECT bikeNum,userName,standName,note FROM bikes LEFT JOIN users ON bikes.currentUser=users.userId LEFT JOIN stands ON bikes.currentStand=stands.standId ORDER BY bikeNum");
      $historyInfo="<h3>Current network usage:</h3><ul>";
      while($row=$result->fetch_assoc())
         {
         $historyInfo.="<li>".$row["bikeNum"]." - ";
         if($row["standName"]!=NULL)
            {
            $historyInfo.=$row["standName"];
            }
         else
            {
            $historyInfo.=$row["userName"];
            }
         if ($row["note"]) $historyInfo.=" (".$row["note"].")";
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
      }
   if (!$result->num_rows) $bicycles="";
   if (!isset($codes)) $codes="";
   else $codes=array("codes"=>$codes);
   response($bicycles,0,$codes,0);
}

function revert($userId,$bikeNum)
{

   global $db;

   $standId=0;
   $result = $db->query("SELECT currentUser FROM bikes WHERE bikeNum=$bikeNum AND currentUser IS NOT NULL'");
   if (!$result->num_rows)
      {
      response("Bicycle $bikeNum is not rented right now. Revert not successful!",ERROR);
      return;
      }
   $result = $db->query("SELECT parameter,standName FROM stands LEFT JOIN history ON standId=parameter WHERE bikeNum=$bikeNum AND action='RETURN' ORDER BY time DESC LIMIT 1");
   if ($result->num_rows==1)
      {
      $row = $result->fetch_assoc();
      $standId=$row["parameter"];
      $stand=$row["standName"];
      }
   $result = $db->query("SELECT parameter FROM history WHERE bikeNum=$bikeNum AND action='RENT' ORDER BY time DESC LIMIT 1,1");
   if ($result->num_rows==1)
      {
      $row = $result->fetch_assoc();
      $code=str_pad($row["parameter"],4,"0",STR_PAD_LEFT);
      }
   if ($standId and $code)
      {
      $result = $db->query("UPDATE bikes SET currentUser=NULL,currentStand=$standId,currentCode=$code where bikeNum=$bikeNum");
      $result = $db->query("INSERT INTO history SET userId=$userId,bikeNum=$bikeNum,action='REVERT',parameter='$standId|$code'");
      $result = $db->query("INSERT INTO history SET userId=0,bikeNum=$bikeNum,action='RENT',parameter=$code");
      $result = $db->query("INSERT INTO history SET userId=0,bikeNum=$bikeNum,action='RETURN',parameter=$standId");
      response('<h3>Bicycle '.$bikeNum.' reverted to <span class="label label-primary">'.$stand.'</span> with code <span class="label label-primary">'.$code.'</span>.</h3>');
      }
   else
      {
      response("No last stand or code for bicycle $bikeNum found. Revert not successful!",ERROR);
      }

}

function register($number,$code,$checkcode,$fullname,$email,$password,$password2,$existing)
{
   global $db, $dbPassword, $countryCode, $systemURL;

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
      response("Password do not match. Please correct and try again.",ERROR);
      }
   $result=$db->query("SELECT parameter FROM history WHERE userId=0 AND bikeNum=0 AND action='REGISTER' AND parameter='$parametercheck' ORDER BY time DESC LIMIT 1");
   if ($result->num_rows==1)
      {
      if (!$existing) // new user registration
         {
         $result=$db->query("INSERT INTO users SET userName='$fullname',password=SHA2('$password',512),mail='$email',number='$number',privileges=0");
         $userId=$db->conn->insert_id;
         sendConfirmationEmail($email);
         response("You have been successfully registered. Please, check your email and read the instructions to finish your registration..");
         }
      else // existing user, password change
         {
         $result=$db->query("SELECT userId FROM users WHERE number='$number'");
         $row=$result->fetch_assoc();
         $userId=$row["userId"];
         $result=$db->query("UPDATE users SET password=SHA2('$password',512) WHERE userId='$userId'");
         response('Password successfully changed. Your username is your phone number. Continue to <a href="'.$systemURL.'">login</a>.');
         }
      }
   else
      {
      response("Problem with the SMS code entered. Please check and try again.",ERROR);
      }

}

function login($number,$password)
{
   global $db,$systemURL,$countryCode;

   $number=$db->conn->real_escape_string(trim($number));
   $password=$db->conn->real_escape_string(trim($password));
   $number=str_replace(" ","",$number); $number=str_replace("-","",$number); $number=str_replace("/","",$number);
   $number=$countryCode.substr($number,1,strlen($number));

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

function checksession()
{
   global $db,$systemURL;

   $result=$db->query("DELETE FROM sessions WHERE timeStamp<='".time()."'");
   if (isset($_COOKIE["loguserid"]) AND isset($_COOKIE["logsession"]))
      {
      $userid=$db->conn->real_escape_string(trim($_COOKIE["loguserid"]));
      $session=$db->conn->real_escape_string(trim($_COOKIE["logsession"]));
      $result=$db->query("SELECT sessionId FROM sessions WHERE userId='$userid' AND sessionId='$session' AND timeStamp>'".time()."'");
      if ($result->num_rows==1)
         {
         $timestamp=time()+86400*14;
         $result=$db->query("UPDATE sessions SET timeStamp='$timestamp' WHERE userId='$userid' AND sessionId='$session'");
         $db->conn->commit();
         }
      else
         {
         $result=$db->query("DELETE FROM sessions WHERE userId='$userid' OR sessionId='$session'");
         $db->conn->commit();
         setcookie("loguserid","",time()-86400);
         setcookie("logsession","",time()-86400);
         header("HTTP/1.1 301 Moved permanently");
         header("Location: ".$systemURL."?error=2");
         header("Connection: close");
         exit;
         }
      }
   else
      {
      header("HTTP/1.1 301 Moved permanently");
      header("Location: ".$systemURL."?error=2");
      header("Connection: close");
      exit;
      }

}

function isloggedin()
{
   global $db;
   if (isset($_COOKIE["loguserid"]) AND isset($_COOKIE["logsession"]))
      {
      $userid=$db->conn->real_escape_string(trim($_COOKIE["loguserid"]));
      $session=$db->conn->real_escape_string(trim($_COOKIE["logsession"]));
      $result=$db->query("SELECT sessionId FROM sessions WHERE userId='$userid' AND sessionId='$session' AND timeStamp>'".time()."'");
      if ($result->num_rows==1) return 1;
      else return 0;
      }
   return 0;

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
      response("Sorry, this command is only available for the privileged users.",ERROR);
      exit;
      }
}

function smscode($number)
{

   global $db, $gatewayId, $gatewayKey, $gatewaySenderNumber, $countryCode;
   srand();

   $number=str_replace(" ","",$number); $number=str_replace("-","",$number); $number=str_replace("/","",$number);
   $number=$countryCode.substr($number,1,strlen($number));
   $number = $db->conn->real_escape_string($number);
   $userexists=0;
   $result = $db->query("SELECT userId FROM users WHERE number='$number'");
   if ($result->num_rows) $userexists=1;

   $smscode=chr(rand(65,90)).chr(rand(65,90))." ".rand(100000,999999);
   $smscodenormalized=str_replace(" ","",$smscode);
   $checkcode=md5("WB".$number.$smscodenormalized);
   if (!$userexists) $text="Enter this code to register: ".$smscode;
   else $text="Enter this code to change password: ".$smscode;
   $text=$db->conn->real_escape_string($text);

   $result = $db->query("INSERT INTO sent SET number='$number',text='$text'");
   $result = $db->query("INSERT INTO history SET userId=0,bikeNum=0,action='REGISTER',parameter='$number;$smscodenormalized;$checkcode'");

   if (DEBUG===TRUE)
      {
      response($number,0,array("checkcode"=>$checkcode,"smscode"=>$smscode,"existing"=>$userexists));
      }
   else
      {
      $s = substr(md5($gatewayKey.$number),10,11);
      $text = substr($text,0,160);
      $um = urlencode($text);
      fopen("http://as.eurosms.com/sms/Sender?action=send1SMSHTTP&i=$gatewayId&s=$s&d=1&sender=$gatewaySenderNumber&number=$number&msg=$um","r");
      response($number,0,array("checkcode"=>$checkcode,"existing"=>$userexists));
      }
}

function mapgetmarkers()
{
   global $db;

   $jsoncontent=array();
   $result = $db->query("SELECT standId,count(bikeNum) AS bikecount,standDescription,standName,standPhoto,longitude AS lon, latitude AS lat FROM stands LEFT JOIN bikes on bikes.currentStand=stands.standId WHERE stands.serviceTag=0 GROUP BY standName ORDER BY standName");
   while($row = $result->fetch_assoc())
      {
      $jsoncontent[]=$row;
      }
   echo json_encode($jsoncontent);
}

function mapgetlimit($userId)
{
   global $db;

   if (!isloggedin()) response("");
   $result = $db->query("SELECT count(*) as countRented FROM bikes where currentUser=$userId");
   $row = $result->fetch_assoc();
   $rented= $row["countRented"];

   $result = $db->query("SELECT userLimit FROM limits where userId=$userId");
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

   $result = $db->query("INSERT INTO geolocation SET userId='$userid',latitude='$lat',longitude='$long'");

   response("");

}

?>