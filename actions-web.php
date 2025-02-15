<?php
require("common.php");

function response($message, $error = 0, $additional = '', $log = 1)
{
    global $db, $user, $auth;
    $json = array('error' => $error, 'content' => $message);
    if (is_array($additional)) {
        foreach ($additional as $key => $value) {
            $json[$key] = $value;
        }
    }
    $json = json_encode($json);
    if ($log == 1 and $message) {
        $userid = $auth->getUserId();

        $number = $user->findPhoneNumber($userid);
        logresult($number, $message);
    }
    echo $json;
    exit;
}

function listbikes($stand)
{
    global $db, $configuration, $standRepository;

    $stacktopbike = false;
    $stand = $db->escape($stand);
    if ($_ENV['FORCE_STACK'] === 'true') {
        $result = $db->query("SELECT standId FROM stands WHERE standName='$stand'");
        $row = $result->fetch_assoc();
        $stacktopbike = $standRepository->findLastReturnedBikeOnStand((int)$row['standId']);
        $stacktopbike = is_null($stacktopbike) ? false : $stacktopbike;
    }
    $result = $db->query("SELECT bikeNum FROM bikes LEFT JOIN stands ON bikes.currentStand=stands.standId WHERE standName='$stand'");
    while ($row = $result->fetch_assoc()) {
        $bikenum = $row['bikeNum'];
        $result2 = $db->query("SELECT note FROM notes WHERE bikeNum='$bikenum' AND deleted IS NULL ORDER BY time DESC");
        $note = '';
        while ($row = $result2->fetch_assoc()) {
            $note .= $row['note'] . '; ';
        }
        $note = substr($note, 0, strlen($note) - 2); // remove last two chars - comma and space
        if ($note) {
            $bicycles[] = '*' . $bikenum; // bike with note / issue
            $notes[] = $note;
        } else {
            $bicycles[] = $bikenum;
            $notes[] = '';
        }
    }
    if (!$result->num_rows) {
        $bicycles = '';
        $notes = '';
    }
    response($bicycles, 0, array('notes' => $notes, 'stacktopbike' => $stacktopbike), 0);
}

function removenote($userId, $bikeNum)
{
    global $db;

    $result = $db->query("DELETE FROM notes WHERE bikeNum=$bikeNum LIMIT XXXX");
    response(_('Note for bike') . ' ' . $bikeNum . ' ' . _('deleted') . '.');
}

function userbikes($userId)
{
    global $db, $auth;
    if (!$auth->isLoggedIn()) {
        response('');
    }

    $result = $db->query("SELECT bikeNum,currentCode FROM bikes WHERE currentUser=$userId ORDER BY bikeNum");
    while ($row = $result->fetch_assoc()) {
        $bikenum = $row['bikeNum'];
        $bicycles[] = $bikenum;
        $codes[] = str_pad($row['currentCode'], 4, '0', STR_PAD_LEFT);
        // get rented seconds and the old code
        $result2 = $db->query("SELECT TIMESTAMPDIFF(SECOND, time, NOW()) as rentedSeconds, parameter FROM history WHERE bikeNum=$bikenum AND action IN ('RENT','FORCERENT') ORDER BY time DESC LIMIT 2");

        $row2 = $result2->fetchAssoc();
        $rentedseconds[] = $row2['rentedSeconds'];

        $row2 = $result2->fetchAssoc();
        $oldcodes[] = str_pad($row2['parameter'], 4, '0', STR_PAD_LEFT);
    }

    if (!$result->num_rows) {
        $bicycles = '';
    }

    if (!isset($codes)) {
        $codes = '';
    } else {
        $codes = array('codes' => $codes, 'oldcodes' => $oldcodes, 'rentedseconds' => $rentedseconds);
    }

    response($bicycles, 0, $codes, 0);
}

function checkprivileges($userid)
{
    global $db, $user;
    $privileges = $user->findPrivileges($userid);
    if ($privileges < 1) {
        response(_('Sorry, this command is only available for the privileged users.'), ERROR);
        exit;
    }
}

function trips($userId, $bike = 0)
{
    global $db;
    $bikeNum = intval($bike);
    if ($bikeNum) {
        $result = $db->query("SELECT longitude,latitude FROM `history` LEFT JOIN stands ON stands.standid=history.parameter WHERE bikenum=$bikeNum AND action='RETURN' ORDER BY time DESC");
        while ($row = $result->fetch_assoc()) {
            $jsoncontent[] = array('longitude' => $row['longitude'], 'latitude' => $row['latitude']);
        }
    } else {
        $result = $db->query("SELECT bikeNum,longitude,latitude FROM `history` LEFT JOIN stands ON stands.standid=history.parameter WHERE action='RETURN' ORDER BY bikeNum,time DESC");
        $i = 0;
        while ($row = $result->fetch_assoc()) {
            $bikenum = $row['bikeNum'];
            $jsoncontent[$bikenum][] = array('longitude' => $row['longitude'], 'latitude' => $row['latitude']);
        }
    }
    echo json_encode($jsoncontent); // TODO change to response function
}

function validatecoupon($userid, $coupon)
{
    global $db, $creditSystem;
    if ($creditSystem->isEnabled() == false) {
        return;
    }
    // if credit system disabled, exit
    $result = $db->query("SELECT coupon,value FROM coupons WHERE coupon='" . $coupon . "' AND status<'2'");
    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $value = $row['value'];
        $result = $db->query("UPDATE credit SET credit=credit+'" . $value . "' WHERE userId='" . $userid . "'");
        $result = $db->query("INSERT INTO history SET userId=$userid,bikeNum=0,action='CREDITCHANGE',parameter='" . $value . '|add+' . $value . '|' . $coupon . "'");
        $result = $db->query("UPDATE coupons SET status='2' WHERE coupon='" . $coupon . "'");
        response('+' . $value . ' ' . $creditSystem->getCreditCurrency() . '. ' . _('Coupon') . ' ' . $coupon . ' ' . _('has been redeemed') . '.');
    }
    response(_('Invalid coupon, try again.'), 1);
}

function changecity($userid, $city)
{
    global $db, $configuration;

    if (in_array($city, $configuration->get('cities'))) {
        $result = $db->query("UPDATE users SET city='$city' WHERE userId=" . $userid);
        response('City changed');
    }
    response(_('Invalid City.'), 1);
}


function mapgetmarkers($userId)
{
    global $db, $configuration, $user;
	$filtercity = '';
    if ($configuration->get('cities')) {
        if ($userId != 0) {
            $filtercity = ' AND city = "' . $user->findCity($userId) . '" ';
        } else {
            $filtercity = "";
        }
    }
    $jsoncontent = array();
    $result = $db->query('SELECT standId,count(bikeNum) AS bikecount,standDescription,standName,standPhoto,longitude AS lon, latitude AS lat FROM stands LEFT JOIN bikes on bikes.currentStand=stands.standId WHERE stands.serviceTag=0 '.$filtercity.' GROUP BY standName ORDER BY standName');
    while ($row = $result->fetch_assoc()) {
        $jsoncontent[] = $row;
    }
    echo json_encode($jsoncontent); // TODO proper response function
}

function mapgetlimit($userId)
{
    global $db, $auth, $creditSystem;

    if (!$auth->isLoggedIn()) {
        response('');
    }

    $result = $db->query("SELECT count(*) as countRented FROM bikes where currentUser=$userId");
    $row = $result->fetch_assoc();
    $rented = $row['countRented'];

    $result = $db->query("SELECT userLimit FROM limits where userId=$userId");
    $row = $result->fetch_assoc();
    $limit = $row['userLimit'];

    $currentlimit = $limit - $rented;

    $userCredit = $creditSystem->getUserCredit($userId);

    echo json_encode(array('limit' => $currentlimit, 'rented' => $rented, 'usercredit' => $userCredit));
}

function mapgeolocation($userid, $lat, $long)
{
    global $db;

    $result = $db->query("INSERT INTO geolocation SET userId='$userid',latitude='$lat',longitude='$long'");

    response('');
}; // TODO for admins: show bikes position on map depending on the user (allowed) geolocation, do not display user bikes without geoloc
