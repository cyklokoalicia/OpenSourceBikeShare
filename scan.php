<?php

use BikeShare\Authentication\Auth;
use BikeShare\Db\DbInterface;
use BikeShare\Rent\RentSystemQR;
use BikeShare\User\User;
use Psr\Log\LoggerInterface;

require_once 'vendor/autoload.php';
require("config.php");
require('actions-qrcode.php');

/**
 * @var DbInterface $db
 * @var LoggerInterface $logger
 */
$user = new User($db);
$auth = new Auth($db);

$auth->refreshSession();
$userid = $auth->getUserId();
$session = $auth->getSessionId();

if (!$auth->isLoggedIn()) {
    response("<h3>" . _('You are not logged in.') . "</h3>", ERROR);
}

$rentSystem = new RentSystemQR();

$request = substr($_SERVER["REQUEST_URI"], strpos($_SERVER["REQUEST_URI"], ".php") + 5);
$request = explode("/", $request);
$action = $request[0];
if (isset($request[1])) {
    $parameter = $request[1];
} else {
    // mangled QR code, clear action
    $action = "";
}

switch ($action) {
    case "rent":
        logrequest($userid, $action);
        $bikeno = $parameter;
        checkbikeno($bikeno);
        if (!empty($_POST['rent']) && $_POST['rent'] == "yes") {
            $rentSystem->rentBike($userid, $bikeno);
        } else {
            showrentform($userid, $bikeno);
        }
        break;
    case "return":
        logrequest($userid, $action);
        $stand = $parameter;
        checkstandname($stand);
        $rentSystem->returnBike($userid, 0, $stand);
        break;
    default:
        unrecognizedqrcode();
}
