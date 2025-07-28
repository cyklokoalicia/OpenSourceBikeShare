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
}

function mapgetmarkers($userId)
{
    global $db, $cityRepository, $user;
	$filterCity = '';
    if (!empty($cityRepository->findAvailableCities()) && !empty($userId)) {
        $filterCity = ' AND city = "' . $user->findCity($userId) . '" ';
    }
    $jsoncontent = array();
    $result = $db->query('SELECT standId,count(bikeNum) AS bikecount,standDescription,standName,standPhoto,longitude AS lon, latitude AS lat FROM stands LEFT JOIN bikes on bikes.currentStand=stands.standId WHERE stands.serviceTag=0 '.$filterCity.' GROUP BY standName ORDER BY standName');
    while ($row = $result->fetch_assoc()) {
        $jsoncontent[] = $row;
    }
    echo json_encode($jsoncontent); // TODO proper response function
}
