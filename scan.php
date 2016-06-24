<?php
require("config.php");
require("db-rb-setup.php");

R::begin();

require('actions-qrcode.php');

if (isset($_COOKIE["loguserid"])) {
    $userid=$_COOKIE["loguserid"];
} else {
    $userid=0;
}
if (isset($_COOKIE["logsession"])) {
    $session=$_COOKIE["logsession"];
}
$request=substr($_SERVER["REQUEST_URI"], strpos($_SERVER["REQUEST_URI"], ".php")+5);
$request=explode("/", $request);
$action=$request[0];
if (isset($request[1])) {
    $parameter=$request[1];
} else {
    $action=""; // mangled QR code, clear action
}
switch ($action) {
    case "rent":
        logrequest($userid, $action);
        checksession();
        $bikenum=$parameter;
        checkbikeno($bikenum);
        rentbike($userid, $bikenum);
        break;
    case "return":
        logrequest($userid, $action);
        checksession();
        $stand=$parameter;
        checkstandname($stand);
        returnbike($userid, 0, $stand);
        break;
    default:
        logrequest($userid, $action);
        unrecognizedqrcode($userid);
}

R::close();
