<?php

require_once 'vendor/autoload.php';

function logrequest($userid)
{
    global $user, $db, $requestStack;

    $number = $user->findPhoneNumber($userid);

    $db->query(
        "INSERT INTO received SET sms_uuid='', sender=:number ,receive_time=:receive_time, sms_text= :sms_text, ip=:ip",
        [
            'number' => $number,
            'receive_time' => date('Y-m-d H:i:s'),
            'sms_text' => $requestStack->getCurrentRequest()->server->get('REQUEST_URI'),
            'ip' => $requestStack->getCurrentRequest()->server->get('REMOTE_ADDR'),
        ]
    );
}

function logresult($userid, $text)
{
    global $db;

    $userid = $db->escape($userid);
    $logtext = "";
    if (is_array($text)) {
        foreach ($text as $value) {
            $logtext .= $value . "; ";
        }
    } else {
        $logtext = $text;
    }

    $logtext = substr(strip_tags($db->escape($logtext)), 0, 200);

    $db->query("INSERT INTO sent SET number='$userid',text='$logtext'");
}

function checkbikeno($bikeNum)
{
    global $db;
    $bikeNum = intval($bikeNum);
    $result = $db->query("SELECT bikeNum FROM bikes WHERE bikeNum=$bikeNum");
    if (!$result->num_rows) {
        response('<h3>Bike ' . $bikeNum . ' does not exist!</h3>', ERROR);
    }
}

function checkstandname($stand)
{
    global $db;
    $standname = trim(strtoupper($stand));
    $result = $db->query("SELECT standName FROM stands WHERE standName='$standname'");
    if (!$result->num_rows) {
        response('<h3>' . _('Stand') . ' ' . $standname . ' ' . _('does not exist') . '!</h3>', ERROR);
    }
}