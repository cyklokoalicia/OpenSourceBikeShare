<?php

use BikeShare\Rent\RentSystemInterface;
use Symfony\Component\HttpFoundation\Request;

require_once 'vendor/autoload.php';
require_once 'actions-qrcode.php';

$userid = $auth->getUserId();

if (!$auth->isLoggedIn()) {
    response("<h3>" . _('You are not logged in.') . "</h3>", ERROR);
}

/**
 * @var RentSystemInterface $rentSystem
 */
$rentSystem = $rentSystemFactory->getRentSystem('qr');

$requestUri = $requestStack->getCurrentRequest()->server->get("REQUEST_URI");

$request = substr($requestUri, strpos($requestUri, ".php") + 5);
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
        if (
            $requestStack->getCurrentRequest()->isMethod(Request::METHOD_POST)
            && $requestStack->getCurrentRequest()->request->has('rent')
            && $requestStack->getCurrentRequest()->request->get('rent') === "yes"
        ) {
            $result = $rentSystem->rentBike($userid, $bikeno);
            response($result['content'], $result['error'], 0);
        } else {
            showrentform($userid, $bikeno);
        }
        break;
    case "return":
        logrequest($userid, $action);
        $stand = $parameter;
        checkstandname($stand);
        $result = $rentSystem->returnBike($userid, 0, $stand);
        response($result['content'], $result['error'], 0);
        break;
    default:
        unrecognizedqrcode();
}
