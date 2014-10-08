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

   log_sendsms($number,$text);
   if (DEBUG===TRUE)
      {
      echo $number,' -&gt ',$text,'<br />';
      }
   else
      {
      $text=substr($text,0,160);
      $sms->Send($number,$text);
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

   $result = $db->query("SELECT userId FROM users where number=$number");
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
   global $db;

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
         sendEmail($admins[$i]["mail"],$message,"");
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
                        response("Some problem occured!",ERROR);
                        return FALSE;
                }
        } else error("key not fetched");

        if ($result = $db->query("UPDATE limits SET userLimit=1 where userId=$userId")) {
        } else error("update limit failed");

        if ($result = $db->query("DELETE from registration where userId='$userId'")) {
        } else error("delete registration failed");

        response("Your account has been activated. Welcome!");

}

?>