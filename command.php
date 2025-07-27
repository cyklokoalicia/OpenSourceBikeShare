<?php

use BikeShare\Rent\RentSystemFactory;
use BikeShare\Rent\RentSystemInterface;
use Symfony\Component\HttpFoundation\RequestStack;

require_once 'vendor/autoload.php';
require_once 'actions-web.php';

$userid = $auth->getUserId();

/**
 * @var RentSystemInterface $rentSystem
 * @var RequestStack $requestStack
 * @var RentSystemFactory $rentSystemFactory
 */
$rentSystem = $rentSystemFactory->getRentSystem('web');

$request = $requestStack->getCurrentRequest();
$action = $request->query->get('action', '');

switch ($action) {
    case "return":
        logrequest($userid, $action);
        $bikeno = trim($request->query->get("bikeno", ''));
        $stand = trim($request->query->get("stand", ''));
        $note = "";
        if ($request->query->has("note")) {
            $note = trim($request->query->get("note", ''));  
        } 
        checkbikeno($bikeno);
        checkstandname($stand);
        $rentSystem->returnBike($userid, $bikeno, $stand, $note);
        break;
    case "validatecoupon":
        logrequest($userid, $action);
        $coupon = trim($request->query->get("coupon", ''));
        validatecoupon($userid, $coupon);
        break;
    case "changecity":
        logrequest($userid, $action);
        $city = trim($request->query->get("city", ''));
        changecity($userid, $city);
        break;
    case "forcerent":
        logrequest($userid, $action);
        checkprivileges($userid);
        $bikeno = trim($request->query->get("bikeno", ''));
        checkbikeno($bikeno);
        $rentSystem->rentBike($userid, $bikeno, true);
        break;
    case "forcereturn":
        logrequest($userid, $action);
        checkprivileges($userid);
        $bikeno = trim($request->query->get("bikeno", ''));
        $stand = trim($request->query->get("stand", ''));
        $note = "";
        if ($request->query->has("note")) {
            $note = trim($request->query->get("note", ''));
        }
        checkbikeno($bikeno);
        checkstandname($stand);
        $rentSystem->returnBike($userid, $bikeno, $stand, $note, TRUE);
        break;
    case "removenote":
        logrequest($userid, $action);
        checkprivileges($userid);
        $bikeno = trim($request->query->get("bikeno", ''));
        checkbikeno($bikeno);
        removenote($userid, $bikeno);
        break;
    case "revert":
        logrequest($userid, $action);
        $bikeno = trim($request->query->get("bikeno", ''));
        checkprivileges($userid);
        checkbikeno($bikeno);
        $rentSystem->revertBike($userid, $bikeno);
        break;
    case "trips":
        logrequest($userid, $action);
        checkprivileges($userid);
        if ($request->query->get("bikeno", '')) {
            $bikeno = trim($request->query->get("bikeno", ''));
            checkbikeno($bikeno);
            trips($userid, $bikeno);
        } else trips($userid);
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
        $lat = floatval(trim($request->query->get("lat", '')));
        $long = floatval(trim($request->query->get("long", '')));
        mapgeolocation($userid, $lat, $long);
        break;
}
