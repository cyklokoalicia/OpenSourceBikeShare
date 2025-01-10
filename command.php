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
   case "list":
      $stand=trim($_GET["stand"]);
      listbikes($stand);
      break;
   case "rent":
      logrequest($userid,$action);
      $bikeno=trim($_GET["bikeno"]);
      checkbikeno($bikeno);
       $rentSystem->rentBike($userid,$bikeno);
      break;
   case "return":
      logrequest($userid,$action);
      $bikeno=trim($_GET["bikeno"]);
      $stand=trim($_GET["stand"]);
      $note="";
      if (isset($_GET["note"])) $note=trim($_GET["note"]);
      checkbikeno($bikeno); checkstandname($stand);
       $rentSystem->returnBike($userid,$bikeno,$stand,$note);
      break;
   case "validatecoupon":
      logrequest($userid,$action);
      $coupon=trim($_GET["coupon"]);
      validatecoupon($userid,$coupon);
      break;
	case "changecity":
      logrequest($userid,$action);
      $city=trim($_GET["city"]);
      changecity($userid,$city);
      break;
   case "forcerent":
      logrequest($userid,$action);
      checkprivileges($userid);
      $bikeno=trim($_GET["bikeno"]);
      checkbikeno($bikeno);
      $rentSystem->rentBike($userid,$bikeno,true);
      break;
   case "forcereturn":
      logrequest($userid,$action);
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
      checkprivileges($userid);
      $bikeno = trim($_GET["bikeno"]);
      checkbikeno($bikeno);
      removenote($userid,$bikeno);
      break;
   case "revert":
      logrequest($userid,$action);
      $bikeno=trim($_GET["bikeno"]);
      checkprivileges($userid);
      checkbikeno($bikeno);
      $rentSystem->revertBike($userid, $bikeno);
      break;
   case "trips":
      logrequest($userid,$action);
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
