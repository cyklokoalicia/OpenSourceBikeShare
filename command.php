<?php
require("config.php");
require("db.class.php");
require('actions-web.php');

$db=new Database($dbserver,$dbuser,$dbpassword,$dbname);
$db->connect();

if (isset($_COOKIE["loguserid"])) $userid=$_COOKIE["loguserid"];
else $userid=0;
if (isset($_COOKIE["logsession"])) $session=$_COOKIE["logsession"];
$action=trim($_GET["action"]);

switch($action)
   {
   case "smscode":
      $number=trim($_GET["number"]);
      smscode($number);
      break;
   case "register":
      $number=trim($_GET["validatednumber"]);
      $smscode=trim($_GET["smscode"]);
      $checkcode=trim($_GET["checkcode"]);
      $fullname=trim($_GET["fullname"]);
      $email=trim($_GET["email"]);
      $password=trim($_GET["password"]);
      $password2=trim($_GET["password2"]);
      $existing=trim($_GET["existing"]);
      register($number,$smscode,$checkcode,$fullname,$email,$password,$password2,$existing);
      break;
   case "login":
      $number=trim($_POST["number"]);
      $password=trim($_POST["password"]);
      login($number,$password);
      break;
   case "logout":
      logout();
      break;
   case "list":
      $stand=trim($_GET["stand"]);
      listbikes($stand);
      break;
   case "rent":
      logrequest($userid,$action);
      checksession();
      $bikeno=trim($_GET["bikeno"]);
      checkbikeno($bikeno);
      rent($userid,$bikeno);
      break;
   case "return":
      logrequest($userid,$action);
      checksession();
      $bikeno=trim($_GET["bikeno"]);
      $stand=trim($_GET["stand"]);
      $note="";
      if (isset($_GET["note"])) $note=trim($_GET["note"]);
      checkbikeno($bikeno); checkstandname($stand);
      returnBike($userid,$bikeno,$stand,$note);
      break;
   case "forcerent":
      logrequest($userid,$action);
      checksession();
      checkprivileges($userid);
      $bikeno=trim($_GET["bikeno"]);
      checkbikeno($bikeno);
      rent($userid,$bikeno,TRUE);
      break;
   case "forcereturn":
      logrequest($userid,$action);
      checksession();
      checkprivileges($userid);
      $bikeno=trim($_GET["bikeno"]);
      $stand=trim($_GET["stand"]);
      $note="";
      if (isset($_GET["note"])) $note=trim($_GET["note"]);
      checkbikeno($bikeno); checkstandname($stand);
      returnBike($userid,$bikeno,$stand,$note,TRUE);
      break;
   case "where":
      logrequest($userid,$action);
      checksession();
      $bikeno=trim($_GET["bikeno"]);
      checkbikeno($bikeno);
      where($userid,$bikeno);
      break;
   case "removenote":
      logrequest($userid,$action);
      checksession();
      checkprivileges($userid);
      checkbikeno($bikeno);
      removenote($userid,$bikeno);
      break;
   case "revert":
      logrequest($userid,$action);
      checksession();
      $bikeno=trim($_GET["bikeno"]);
      checkprivileges($userid);
      checkbikeno($bikeno);
      revert($userid,$bikeno);
      break;
   case "last":
      logrequest($userid,$action);
      checksession();
      checkprivileges($userid);
      if ($_GET["bikeno"])
         {
         $bikeno=trim($_GET["bikeno"]);
         checkbikeno($bikeno);
         last($userid,$bikeno);
         }
      else last($userid);
      break;
   case "trips":
      logrequest($userid,$action);
      checksession();
      checkprivileges($userid);
      if ($_GET["bikeno"])
         {
         $bikeno=trim($_GET["bikeno"]);
         checkbikeno($bikeno);
         trips($userid,$bikeno);
         }
      else trips($userid);
      break;
   case "userbikes":
      userbikes($userid);
      break;
   case "map:markers":
      mapgetmarkers();
      break;
   case "map:status":
      mapgetlimit($userid);
      break;
   case "map:geolocation":
      $lat=trim($_GET["lat"]);
      $long=trim($_GET["long"]);
      mapgeolocation($userid,$lat,$long);
      break;
   }

?>