<?php
require("config.php");
require("db.class.php");
require('actions-web.php');

$db=new Database($dbserver,$dbuser,$dbpassword,$dbname);
$db->connect();

if (isset($_COOKIE["loguserid"])) $userid=$_COOKIE["loguserid"];
else $userid=0;
if (isset($_COOKIE["logsession"])) $session=$_COOKIE["logsession"];
$action="";
if (isset($_GET["action"])) $action=trim($_GET["action"]);

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
      $useremail=trim($_GET["useremail"]);
      $password=trim($_GET["password"]);
      $password2=trim($_GET["password2"]);
      $existing=trim($_GET["existing"]);
      register($number,$smscode,$checkcode,$fullname,$useremail,$password,$password2,$existing);
      break;
   case "login":
      $number=trim($_POST["number"]);
      $password=trim($_POST["password"]);
      login($number,$password);
      break;
   case "logout":
      logout();
      break;
   case "resetpassword":
      resetpassword($_GET["number"]);
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
   case "validatecoupon":
      logrequest($userid,$action);
      checksession();
      $coupon=trim($_GET["coupon"]);
      validatecoupon($userid,$coupon);
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
   case "stands":
      logrequest($userid,$action);
      checksession();
      checkprivileges($userid);
      liststands();
      break;
   case "userlist":
      logrequest($userid,$action);
      checksession();
      checkprivileges($userid);
      getuserlist();
      break;
   case "userstats":
      logrequest($userid,$action);
      checksession();
      checkprivileges($userid);
      getuserstats();
      break;
   case "edituser":
      logrequest($userid,$action);
      checksession();
      checkprivileges($userid);
      edituser($_GET["edituserid"]);
      break;
   case "saveuser":
      logrequest($userid,$action);
      checksession();
      checkprivileges($userid);
      saveuser($_GET["edituserid"],$_GET["username"],$_GET["email"],$_GET["phone"],$_GET["privileges"],$_GET["limit"]);
      break;
   case "addcredit":
      logrequest($userid,$action);
      checksession();
      checkprivileges($userid);
      addcredit($_GET["edituserid"],$_GET["creditmultiplier"]);
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
   case "couponlist":
      logrequest($userid,$action);
      checksession();
      getcouponlist();
      break;
   case "generatecoupons":
      logrequest($userid,$action);
      checksession();
      generatecoupons($_GET["multiplier"]);
      break;
   case "sellcoupon":
      logrequest($userid,$action);
      checksession();
      sellcoupon($_GET["coupon"]);
      break;
   case "map:markers":
      mapgetmarkers();
      break;
   case "map:status":
      mapgetlimit($userid);
      break;
   case "map:geolocation":
      $lat=floatval(trim($_GET["lat"]));
      $long=floatval(trim($_GET["long"]));
      mapgeolocation($userid,$lat,$long);
      break;
   }

?>