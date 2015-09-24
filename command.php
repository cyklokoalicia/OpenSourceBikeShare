<?php
require("config.php");
require("external/rb.php");
R::setup('mysql:host='.$dbserver.';dbname='.$dbname,$dbuser,$dbpassword);
R::freeze( TRUE );
R::debug( TRUE,2 );
R::addDatabase('localdb','mysql:host='.$dbserver.';dbname='.$dbname,$dbuser,$dbpassword,TRUE);
R::begin();
require('actions-web.php');

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
      $bikenum=trim($_GET["bikeno"]);
      checkbikeno($bikenum);
      rentbike($userid,$bikenum);
      break;
   case "return":
      logrequest($userid,$action);
      checksession();
      $bikenum=trim($_GET["bikeno"]);
      $stand=trim($_GET["stand"]);
      $note="";
      if (isset($_GET["note"])) $note=trim($_GET["note"]);
      checkbikeno($bikenum); checkstandname($stand);
      returnbike($userid,$bikenum,$stand,$note);
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
      $bikenum=trim($_GET["bikeno"]);
      checkbikeno($bikenum);
      rent($userid,$bikenum,TRUE);
      break;
   case "forcereturn":
      logrequest($userid,$action);
      checksession();
      checkprivileges($userid);
      $bikenum=trim($_GET["bikeno"]);
      $stand=trim($_GET["stand"]);
      $note="";
      if (isset($_GET["note"])) $note=trim($_GET["note"]);
      checkbikeno($bikenum); checkstandname($stand);
      returnBike($userid,$bikenum,$stand,$note,TRUE);
      break;
   case "where":
      logrequest($userid,$action);
      checksession();
      $bikenum=trim($_GET["bikeno"]);
      checkbikeno($bikenum);
      where($userid,$bikenum);
      break;
   case "removenote":
      logrequest($userid,$action);
      checksession();
      checkprivileges($userid);
      checkbikeno($bikenum);
      removenote($userid,$bikenum);
      break;
   case "revert":
      logrequest($userid,$action);
      checksession();
      $bikenum=trim($_GET["bikeno"]);
      checkprivileges($userid);
      checkbikeno($bikenum);
      revert($userid,$bikenum);
      break;
   case "last":
      logrequest($userid,$action);
      checksession();
      checkprivileges($userid);
      if ($_GET["bikeno"])
         {
         $bikenum=trim($_GET["bikeno"]);
         checkbikeno($bikenum);
         last($userid,$bikenum);
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
   case "usagestats":
      logrequest($userid,$action);
      checksession();
      checkprivileges($userid);
      getusagestats();
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
         $bikenum=trim($_GET["bikeno"]);
         checkbikeno($bikenum);
         trips($userid,$bikenum);
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