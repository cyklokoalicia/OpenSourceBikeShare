<?php
require("config.php");
require("db.class.php");
require('actions-web.php');

$db=new Database($dbServer,$dbUser,$dbPassword,$dbName);
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
      register($number,$smscode,$checkcode,$fullname,$email,$password,$password2);
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
      checkbikeno($bikeno); checkstandname($stand);
      returnBike($userid,$bikeno,$stand);
      break;
   case "where":
      logrequest($userid,$action);
      checksession();
      $bikeno=trim($_GET["bikeno"]);
      checkbikeno($bikeno);
      where($userid,$bikeno);
      break;
   case "addnote": // TODO
      logrequest($userid,$action);
      checksession();
      checkbikeno($bikeno);
      addnote($userid,$bikeno,$note);
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
      $bikeno=trim($_GET["bikeno"]);
      checkbikeno($bikeno);
      last($userid,$bikeno);
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
   }



?>