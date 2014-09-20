<?php

error_reporting(-1);

require("config.php");

function help()
{
	return "Available commands:\nRENT bikenumber\nRETURN bikenumber standname\nWHERE bikenumber\nINFO standname\nFREE\nNOTE bikenumber problem description";
}
function sendSMS($number, $text)
{

log_sendsms($number,$text);

global $gatewayId, $gatewayKey, $gatewaySenderNumber;
    $s = substr(md5($gatewayKey.$number),10,11);
    $text = substr($text,0,160);
    $um = urlencode($text);
    //fopen("http://as.eurosms.com/sms/Sender?action=send1SMSHTTP&i=$gatewayId&s=$s&d=1&sender=$gatewaySenderNumber&number=$number&msg=$um","r");
    echo $text;
}

function getUser($number)
{
	$mysqli = createDbConnection();

	if ($result = $mysqli->query("SELECT userId FROM users where number=$number")) {
    		if($result->num_rows==1)
		{
			$row = $result->fetch_assoc();
			return $row["userId"];
		}
		return -1;
	}
}

function getPrivileges($userId)
{
	$mysqli = createDbConnection();

	if ($result = $mysqli->query("SELECT privileges FROM users where userId=$userId")) {
    		if($result->num_rows==1)
		{
			$row = $result->fetch_assoc();
			return $row["privileges"];
		}
		return 0;
	}
}


function validateNumber($number)
{
    if(getUser($number)!=-1)
	return true;
    else
	return false;
}

function error($message)
{
	//nieco sa pokazilo
	die($message);
}

function info($number,$stand)
{
        $mysqli = createDbConnection();
        $stand = strtoupper($stand);

        if(!preg_match("/^[A-Z]+[0-9]*$/",$stand))
        {
                sendSMS($number,"The stand name '$stand' has not been recognized. Stands are marked by CAPITALLETTERS.");
                return;
        }
        if ($result = $mysqli->query("SELECT standId FROM stands where standName='$stand'")) {
                if($result->num_rows!=1)
                {
                        sendSMS($number,"Stand '$stand' does not exist.");
                        return;
                }
                $row = $result->fetch_assoc();
                $standId = $row["standId"];
        } else error("stand not retrieved");
        if ($result = $mysqli->query("SELECT * FROM stands where standname='$stand'")) {
                $row = $result->fetch_assoc();
                $standDescription=$row["standDescription"];
                $standLat=$row["latitude"];
                $standLong=$row["longitude"];
                $message=$stand." - ".$standDescription.", GPS: ".$standLong.",".$standLat;
                sendSMS($number,$message);
        } else error("stand not found");
}

/** Validate received SMS - check message for required number of arguments
 * @param string $number sender's phone number
 * @param int $receivedargumentno number of received arguments
 * @param int $requiredargumentno number of requiredarguments
 * @param string $errormessage error message to send back in case of mismatch
 * @param boolean $moreok TRUE if more arguments than required are OK and should not be validated, otherwise FALSE by default
**/
function validateReceivedSMS($number,$receivedargumentno,$requiredargumentno,$errormessage,$moreok=FALSE)
{
   if($receivedargumentno<$requiredargumentno)
      {
      sendSMS($number,"Error. More arguments needed, use command ".$errormessage);
      exit;
      }
   elseif($receivedargumentno>$requiredargumentno AND $moreok===FALSE)
      {
      sendSMS($number,"Error. Less arguments needed, use command ".$errormessage);
      exit;
      }
   return TRUE;
}

function rent($number,$bike)
{

	$userId = getUser($number);
	$mysqli = createDbConnection();

	$bikeNum = intval($bike);

	if ($result = $mysqli->query("SELECT count(*) as countRented FROM bikes where currentUser=$userId")) {
    		$row = $result->fetch_assoc();
		$countRented = $row["countRented"];
	} else error("count not retrieved");

	if ($result = $mysqli->query("SELECT userLimit FROM limits where userId=$userId")) {
    		$row = $result->fetch_assoc();
		$limit = $row["userLimit"];
	} else error("limit not retrieved");

	if($countRented >= $limit)
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

	if ($result = $mysqli->query("SELECT currentUser,currentCode,note FROM bikes where bikeNum=$bikeNum")) {
    		if($result->num_rows!=1)
		{
			sendSMS($number,"Bike $bikeNum does not exist.");
			return;
		}
    		$row = $result->fetch_assoc();
		$currentCode = sprintf("%04d",$row["currentCode"]);
		$currentUser= $row["currentUser"];
		$note= $row["note"];
	} else error("bike code not retrieved");

	$newCode = sprintf("%04d",rand(100,9900));//do not create a code with more than one leading zero or more than two leading 9s (kind of unusual/unsafe).

	if($currentUser==$userId)
	{
		sendSMS($number,"You already rented the bike $bikeNum. Code is $currentCode. Return the bike with command: RETURN bikenumber standname.");
		return;
	}

	if($currentUser!=0)
	{
		sendSMS($number,"The bike $bikeNum is already rented.");
		return;
	}

	$message="Bike $bikeNum: Open with code $currentCode, change code immediately to $newCode (open,rotate metal part,set new code,rotate metal part back).";
	if($note!="")
	{
		$message.="(bike note:".$note.")";
	}
	sendSMS($number,$message);

	if ($result = $mysqli->query("UPDATE bikes SET currentUser=$userId,currentCode=$newCode,currentStand=NULL where bikeNum=$bikeNum")) {
	} else error("update failed");
	//echo "RENT success";

	if ($result = $mysqli->query("INSERT INTO history SET userId=$userId,bikeNum=$bikeNum,action='RENT',parameter=$newCode")) {
	} else error("update failed");

}

function returnBike($number,$bike,$stand)
{
	$userId = getUser($number);
	$mysqli = createDbConnection();

	$bikeNum = intval($bike);
	$stand = strtoupper($stand);

	if(!preg_match("/^[A-Z]+[0-9]*$/",$stand))
	{
		sendSMS($number,"The stand name '$stand' has not been recognized. Stands are marked by CAPITALLETTERS.");
		return;
	}


	if ($result = $mysqli->query("SELECT bikeNum FROM bikes where currentUser=$userId ORDER BY bikeNum")) {
		$rentedBikes = $result->fetch_all(MYSQLI_ASSOC);
	} else error("rented bikes not fetched");

	if(count($rentedBikes)==0)
	{
		sendSMS($number,"You have no rented bikes currently.");
		return;
	}

	$listBikes="";
	for($i=0; $i<count($rentedBikes);$i++)
         {
         if($i!=0)
         $listBikes.=$rentedBikes[$i]["bikeNum"];
         if ($i<count($rentedBikes)) $listBikes.=",";
         }

	if ($result = $mysqli->query("SELECT currentCode,note FROM bikes where currentUser=$userId and bikeNum=$bikeNum")) {
    		if($result->num_rows!=1)
		{
			sendSMS($number,"You have not rented the bike $bikeNum. You have rented the following bike(s): $listBikes");
			return;
		}

		$row = $result->fetch_assoc();
		$currentCode = sprintf("%04d",$row["currentCode"]);
		$note= $row["note"];
	} else error("code not retrieved");

	if ($result = $mysqli->query("SELECT standId FROM stands where standName='$stand'")) {
    		if($result->num_rows!=1)
		{
			sendSMS($number,"Stand '$stand' does not exist.");
			return;
		}
    		$row = $result->fetch_assoc();
		$standId = $row["standId"];
	} else error("stand not retrieved");


	if ($result = $mysqli->query("UPDATE bikes SET currentUser=NULL,currentStand=$standId where bikeNum=$bikeNum")) {
	} else error("update failed");


	$message = "You have successfully returned the bike $bikeNum to stand $stand. Make sure you have set the code $currentCode.";
	if($note!="")
	{
		$message.="(bike note:".$note.")";
	}
	$message.="Do not forget to rotate the lockpad to 0000 when leaving.";
	sendSMS($number,$message);
//	echo "RETURN success";

	if ($result = $mysqli->query("INSERT INTO history SET userId=$userId,bikeNum=$bikeNum,action='RETURN',parameter=$standId")) {
	} else error("update failed");

}


function where($number,$bike)
{

	$userId = getUser($number);
	$mysqli = createDbConnection();

	$bikeNum = intval($bike);

	if ($result = $mysqli->query("SELECT number,userName,stands.standName,note FROM bikes LEFT JOIN users on bikes.currentUser=users.userID LEFT JOIN stands on bikes.currentStand=stands.standId where bikeNum=$bikeNum")) {
    		if($result->num_rows!=1)
		{
			sendSMS($number,"Bike $bikeNum does not exist.");
			return;
		}
    		$row = $result->fetch_assoc();
		$phone= $row["number"];
		$userName= $row["userName"];
		$standName= $row["standName"];
		$note= $row["note"];
		if($note!=""){
			$note=" Bike note: $note";
		}
	} else error("bike code not retrieved");

	if($standName!=NULL)
	{
		sendSMS($number,"Bike $bikeNum is at stand $standName.$note");
	}
	else
	{
		sendSMS($number,"Bike $bikeNum is rented by $userName (+$phone).$note");
	}


	//echo "RENT success";

}


function listBikes($number,$stand)
{

	$userId = getUser($number);

	$mysqli = createDbConnection();

	$stand = strtoupper($stand);

	if(!preg_match("/^[A-Z]+[0-9]*$/",$stand))
	{
		sendSMS($number,"The stand name '$stand' has not been recognized. Stands are marked by CAPITALLETTERS.");
		return;
	}

	if ($result = $mysqli->query("SELECT standId FROM stands where standName='$stand'")) {
    		if($result->num_rows!=1)
		{
			sendSMS($number,"Stand '$stand' does not exist.");
			return;
		}
    		$row = $result->fetch_assoc();
		$standId = $row["standId"];
	} else error("stand not retrieved");


	if ($result = $mysqli->query("SELECT bikeNum FROM bikes where currentStand=$standId ORDER BY bikeNum")) {
		$rentedBikes = $result->fetch_all(MYSQLI_ASSOC);
	} else error("bikes on stand not fetched");

	if(count($rentedBikes)==0)
	{
		sendSMS($number,"Stand $stand is empty.");
		return;
	}

	$listBikes="";
	for($i=0; $i<count($rentedBikes);$i++)
	{
		if($i!=0)
			$listBikes.=",";
		$listBikes.=$rentedBikes[$i]["bikeNum"];
	}

	$countBikes = count($rentedBikes);
	sendSMS($number,"$countBikes bike(s) on stand $stand: $listBikes");
}


function freeBikes($number)
{

	$userId = getUser($number);
	$mysqli = createDbConnection();

	if ($result = $mysqli->query("SELECT count(bikeNum) as bikeCount,placeName from bikes join stands on
	bikes.currentStand=stands.standId where stands.serviceTag=0 group by
	placeName having bikeCount>0 order by placeName")) {
		$rentedBikes = $result->fetch_all(MYSQLI_ASSOC);
	} else error("bikes on stand not fetched");

	if(count($rentedBikes)==0)
	{
		sendSMS($number,"No free bikes.");
		return;
	}

	$listBikes="";
	for($i=0; $i<count($rentedBikes);$i++)
	{
		if($i!=0)
			$listBikes.=",";
		$listBikes.=$rentedBikes[$i]["placeName"].":".$rentedBikes[$i]["bikeCount"];
	}

	$countBikes = count($rentedBikes);
	sendSMS($number,"Free bikes counts: $listBikes");
}

function log_sms($sms_uuid, $sender, $receive_time, $sms_text, $ip)
{
	$mysqli = createDbConnection();

	$sms_uuid = $mysqli->real_escape_string($sms_uuid);
	$sender = $mysqli->real_escape_string($sender);
	$receive_time = $mysqli->real_escape_string($receive_time);
	$sms_text = $mysqli->real_escape_string($sms_text);
	$ip = $mysqli->real_escape_string($ip);

        $result = $mysqli->query("SELECT sms_uuid FROM receivedsms WHERE sms_uuid='$sms_uuid'");
        if ($result->num_rows>=100) // sms already exists in DB, possible problem
           {
           //notifyAdmins("Problem with SMS $sms_uuid!",1);
           return FALSE;
           }
        else
           {
           if ($result = $mysqli->query("INSERT INTO receivedsms SET sms_uuid='$sms_uuid',sender='$sender',receive_time='$receive_time',sms_text='$sms_text',ip='$ip'"))
              {
              }
              else error("update failed");
           }


}

function log_sendsms($number, $text)
{
	$mysqli = createDbConnection();

	$number = $mysqli->real_escape_string($number);
	$text = $mysqli->real_escape_string($text);


	if ($result = $mysqli->query("INSERT INTO sentsms SET number='$number',text='$text'")) {
	} else error("update failed");


}

function note($number,$bikeNum,$message)
{

	$userId = getUser($number);
	$mysqli = createDbConnection();
	$bikeNum = intval($bikeNum);


	if ($result = $mysqli->query("SELECT number,userName,stands.standName FROM bikes LEFT JOIN users on bikes.currentUser=users.userID LEFT JOIN stands on bikes.currentStand=stands.standId where bikeNum=$bikeNum")) {
    		if($result->num_rows!=1)
		{
			sendSMS($number,"Bike $bikeNum does not exist.");
			return;
		}
    		$row = $result->fetch_assoc();
		$phone= $row["number"];
		$userName= $row["userName"];
		$standName= $row["standName"];
	} else error("bike code not retrieved");

	if($standName!=NULL)
	{
		$bikeStatus = "B.$bikeNum is at $standName.";
	}
	else
	{
		$bikeStatus = "B.$bikeNum is rented by $userName (+$phone).";
	}

	if ($result = $mysqli->query("SELECT userName from users where number=$number")) {
    		$row = $result->fetch_assoc();
		$reportedBy= $row["userName"];
	} else error("user not retrieved");

	if(!preg_match("/note[\s,\.]+[0-9]+[\s,\.]+(.*)/i",$message ,$matches))
	{
		$userNote="";
	}
	else $userNote=$mysqli->real_escape_string(trim($matches[1]));

	if($userNote=="")
	{
		checkUserPrivileges($number);

		if ($result = $mysqli->query("UPDATE bikes SET note=NULL where bikeNum=$bikeNum")) {
		} else error("update failed");

		sendSMS($number,"Note for bike $bikeNum deleted.");
		return;
	}
	else
	{
		if ($result = $mysqli->query("UPDATE bikes SET note='$userNote' where bikeNum=$bikeNum")) {
		} else error("update failed");

		sendSMS($number,"Note for bike $bikeNum saved.");

		notifyAdmins("Note b.$bikeNum by $reportedBy:".$userNote." ".$bikeStatus);

		return;
	}

}


/**
 * @param int $notificationtype 0 = via SMS, 1 = via email
**/
function notifyAdmins($message,$notificationtype=0)
{
	$mysqli = createDbConnection();

	if ($result = $mysqli->query("SELECT number,mail FROM users where privileges & 2 != 0")) {
		$admins = $result->fetch_all(MYSQLI_ASSOC);
	} else error("admins not fetched");


	for($i=0; $i<count($admins);$i++)
	{
            if ($notificationtype==0)
               {
               sendSMS($admins[$i]["number"],$message);
               }
            else
               {
               sendEmail($admins[$i]["mail"],$message,"");
               }
	}

}


function last($number,$bike)
{

	$userId = getUser($number);
	$mysqli = createDbConnection();

	$bikeNum = intval($bike);

	if ($result = $mysqli->query("SELECT bikeNum FROM bikes where bikeNum=$bikeNum")) {
    		if($result->num_rows!=1)
		{
			sendSMS($number,"Bike $bikeNum does not exist.");
			return;
		}
    	} else error("bike not retrieved");

	if ($result = $mysqli->query("SELECT userName,parameter,standName
FROM `history` join users on history.userid=users.userid left join stands on stands.standid=history.parameter where bikenum=$bikeNum order by time desc
LIMIT 10")) {
		$bikeHistory= $result->fetch_all(MYSQLI_ASSOC);
	} else error("bike history not retrieved");

	$historyInfo="B.$bikeNum:";
	for($i=0; $i<count($bikeHistory);$i++)
	{
		if($i!=0)
			$historyInfo.=",";

		if(($standName=$bikeHistory[$i]["standName"])!=NULL)
		{
			$historyInfo.=$standName;

		}
		else
		{
			$historyInfo.=$bikeHistory[$i]["userName"]."(".$bikeHistory[$i]["parameter"].")";
		}
	}

	sendSMS($number,$historyInfo);

}

function revert($number,$bikenum)
{

        $userId = getUser($number);
        $mysqli = createDbConnection();

        if ($result = $mysqli->query("SELECT parameter FROM stands LEFT JOIN history ON standId=parameter WHERE bikeNum=$bikeNum AND action='RETURN' ORDER BY time DESC LIMIT 1")) {
                if($result->num_rows==1)
                {
                        $row = $result->fetch_assoc();
                        $standId=$row["parameter"];
                        if ($result = $mysqli->query("UPDATE bikes SET currentUser=NULL,currentStand=$standId where bikeNum=$bikeNum")) {
                        } else error("update failed");
                }
        } else {
               sendSMS($number,"No last stand for bicycle $bikenum found. Revert not successful!");
               error("no last stand for bicycle found / revert not successful!");
               }
}

function add($number,$email,$phone,$message)
{

	$userId = getUser($number);
	$mysqli = createDbConnection();

	$phone=intval($phone);
	if($phone<=999999999)
	{
		$phone+=421000000000;
	}

	if ($result = $mysqli->query("SELECT number,mail,userName FROM users where number=$phone OR mail='$email'")) {
    		if($result->num_rows!=0)
		{
    			$row = $result->fetch_assoc();

			$oldPhone= $row["number"];
			$oldName= $row["userName"];
			$oldMail= $row["mail"];

			sendSMS($number,"Contact information conflict: Other user already registered: $oldMail +$oldPhone $oldName");
			return;
		}
	} else error("user info not retrieved");

	if($phone < 421000000000 || $phone > 422000000000 || !preg_match("/add\s+([a-z0-9._%+-]+@[a-z0-9.-]+)\s+\+?[0-9]+\s+(.{2,}\s.{2,})/i",$message ,$matches))
	{
		sendSMS($number,"Contact information is in incorrect format. Usage: ADD king@earth.com 0901456789 Martin Luther King Jr.");
		return;
	}
	$userName=$mysqli->real_escape_string(trim($matches[2]));
	$email=$mysqli->real_escape_string(trim($matches[1]));

	if ($result = $mysqli->query("INSERT into users SET userName='$userName',number=$phone,mail='$email'")) {
	} else error("insert user failed");

	sendConfirmationEmail($email);

	sendSMS($number,"User $userName added. He/She has to read the email and confirm usage rules before using the system.");


}

function sendConfirmationEmail($email)
{
	$subject = 'registracia/registration White Bikes';

	$mysqli = createDbConnection();

	if ($result = $mysqli->query("SELECT userName,userId FROM users where mail='$email'")) {
		$user = $result->fetch_all(MYSQLI_ASSOC);
	} else error("email not fetched");

	$userId =$user[0]["userId"];
	$userKey = hash('sha256', $email.$dbPassword.rand(0,1000000));

	if ($result = $mysqli->query("INSERT into registration SET userKey='$userKey',userId='$userId'")) {
	} else error("insert registration failed");

	if ($result = $mysqli->query("INSERT into limits SET userId='$userId',userLimit=0")) {
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

http://whitebikes.info/sms/agree.php?key=$userKey
";
		sendEmail($email, $subject, $message);
}

function confirmUser($userKey)
{
	$mysqli=createDbConnection();

	$userKey = $mysqli->real_escape_string($userKey);

	if ($result = $mysqli->query("SELECT userId FROM registration where userKey='$userKey'")) {
		if($result->num_rows==1)
		{
			$row = $result->fetch_assoc();
			$userId = $row["userId"];
		}
		else
		{
			echo "Some problem occured!";
			return -1;
		}
	} else error("key not fetched");

	if ($result = $mysqli->query("UPDATE limits SET userLimit=1 where userId=$userId")) {
	} else error("update limit failed");

	if ($result = $mysqli->query("DELETE from registration where userId='$userId'")) {
	} else error("delete registration failed");

	echo "All fine. Welcome!";
}


function createDbConnection()
{
   global $dbServer, $dbUser, $dbPassword, $dbName;
   $result = new mysqli($dbServer, $dbUser, $dbPassword, $dbName);
   if (!$result) die('db connection error!');
   return $result;
}

function sendEmail($email,$subject,$message)
{
   $headers = 'From: info@whitebikes.info' . "\r\n" . 'Reply-To: info@cyklokoalicia.sk' . "\r\n" . 'X-Mailer: PHP/' . phpversion();
   mail($email, $subject, $message, $headers); // @TODO: replace with proper SMTP mailer
}

function checkUserPrivileges($number)
{
   $userId=getUser($number);
   $privileges=getPrivileges($userId);
   if ($privileges==0)
      {
      sendSMS($number,"Sorry, this command is only available for the privileged users.");
      exit;
      }
}

?>
