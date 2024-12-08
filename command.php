<?php

use BikeShare\Rent\RentSystemInterface;

require_once 'vendor/autoload.php';
require_once 'actions-web.php';

$userid = $auth->getUserId();

/**
 * @var RentSystemInterface $rentSystem
 */
$rentSystem = $rentSystemFactory->getRentSystem('web');

$action="";
if (isset($_GET["action"])) $action=trim($_GET["action"]);

switch($action)
   {
   case "smscode":
      $number=trim($_GET["number"]);
      smscode($number);
      break;
   case "register": #"operationId": "user.register",
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
   case "list":
      $stand=trim($_GET["stand"]);
      listbikes($stand);
      break;
   case "rent":
      logrequest($userid,$action);
      $auth->refreshSession();
      $bikeno=trim($_GET["bikeno"]);
      checkbikeno($bikeno);
       $rentSystem->rentBike($userid,$bikeno);
      break;
   case "return":
      logrequest($userid,$action);
      $auth->refreshSession();
      $bikeno=trim($_GET["bikeno"]);
      $stand=trim($_GET["stand"]);
      $note="";
      if (isset($_GET["note"])) $note=trim($_GET["note"]);
      checkbikeno($bikeno); checkstandname($stand);
       $rentSystem->returnBike($userid,$bikeno,$stand,$note);
      break;
   case "validatecoupon":
      logrequest($userid,$action);
      $auth->refreshSession();
      $coupon=trim($_GET["coupon"]);
      validatecoupon($userid,$coupon);
      break;
	case "changecity":
      logrequest($userid,$action);
      $auth->refreshSession();
      $city=trim($_GET["city"]);
      changecity($userid,$city);
      break;
   case "forcerent":
      logrequest($userid,$action);
      $auth->refreshSession();
      checkprivileges($userid);
      $bikeno=trim($_GET["bikeno"]);
      checkbikeno($bikeno);
      $rentSystem->rentBike($userid,$bikeno,true);
      break;
   case "forcereturn":
      logrequest($userid,$action);
      $auth->refreshSession();
      checkprivileges($userid);
      $bikeno=trim($_GET["bikeno"]);
      $stand=trim($_GET["stand"]);
      $note="";
      if (isset($_GET["note"])) $note=trim($_GET["note"]);
      checkbikeno($bikeno); checkstandname($stand);
      $rentSystem->returnBike($userid, $bikeno, $stand, $note, TRUE);
      break;
   case "removenote":
      logrequest($userid,$action);
      $auth->refreshSession();
      checkprivileges($userid);
      $bikeno = trim($_GET["bikeno"]);
      checkbikeno($bikeno);
      removenote($userid,$bikeno);
      break;
   case "revert":
      logrequest($userid,$action);
      $auth->refreshSession();
      $bikeno=trim($_GET["bikeno"]);
      checkprivileges($userid);
      checkbikeno($bikeno);
      revert($userid,$bikeno);
      break;
   case "stands": #"operationId": "stand.get",
      logrequest($userid,$action);
      $auth->refreshSession();
      checkprivileges($userid);
      liststands();
      break;
   case "userlist":
      logrequest($userid,$action);
      $auth->refreshSession();
      checkprivileges($userid);
      getuserlist();
      break;
   case "userstats":
      logrequest($userid,$action);
      $auth->refreshSession();
      checkprivileges($userid);
      getuserstats();
      break;
   case "usagestats":
      logrequest($userid,$action);
      $auth->refreshSession();
      checkprivileges($userid);
      getusagestats();
      break;
   case "edituser":
      logrequest($userid,$action);
      $auth->refreshSession();
      checkprivileges($userid);
      edituser($_GET["edituserid"]);
      break;
   case "saveuser":
      logrequest($userid,$action);
      $auth->refreshSession();
      checkprivileges($userid);
      saveuser($_GET["edituserid"],$_GET["username"],$_GET["email"],$_GET["phone"],$_GET["privileges"],$_GET["limit"]);
      break;
   case "addcredit":
      logrequest($userid,$action);
      $auth->refreshSession();
      checkprivileges($userid);
      addcredit($_GET["edituserid"],$_GET["creditmultiplier"]);
      break;
   case "trips":
      logrequest($userid,$action);
      $auth->refreshSession();
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
      $auth->refreshSession();
      getcouponlist();
      break;
   case "generatecoupons":
      logrequest($userid,$action);
      $auth->refreshSession();
      generatecoupons($_GET["multiplier"]);
      break;
   case "sellcoupon":
      logrequest($userid,$action);
      $auth->refreshSession();
      sellcoupon($_GET["coupon"]);
      break;
   case "map:markers":
      mapgetmarkers($userid);
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
