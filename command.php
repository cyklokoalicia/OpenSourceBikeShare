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
    case "trips":
        logrequest($userid, $action);
        checkprivileges($userid);
        if ($request->query->get("bikeno", '')) {
            $bikeno = trim($request->query->get("bikeno", ''));
            checkbikeno($bikeno);
            trips($userid, $bikeno);
        } else trips($userid);
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
