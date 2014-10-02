<?php

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

?>