<?php
require("external/PHPMailer/PHPMailerAutoload.php");
require("external/htmlpurifier/HTMLPurifier.standalone.php");
$htmlpurconfig=HTMLPurifier_Config::createDefault();
$purifier=new HTMLPurifier($htmlpurconfig);
@$purifier->purify($_GET);
@$purifier->purify($_POST);
@$purifier->purify($_COOKIE);
@$purifier->purify($_FILES);
@$purifier->purify($_SERVER);
$locale=$systemlang.".utf8";
setlocale(LC_ALL, $locale);
putenv("LANG=".$locale);
bindtextdomain("messages", dirname(__FILE__).'/languages');
textdomain("messages");

if (issmssystemenabled()==true) {
    require("connectors/".$connectors["sms"].".php");
} else {
    require("connectors/disabled.php");
}
$sms=new SMSConnector();

function error($message)
{
    R::rollback();
    exit($message);
}

function sendEmail($emailto, $subject, $message)
{
    global $systemname, $systememail, $email;
    $mail=new PHPMailer;
    $mail->isSMTP(); // Set mailer to use SMTP
   //$mail->SMTPDebug  = 2;
    $mail->Host=$email["smtp"]; // Specify main and backup SMTP servers
    $mail->Username=$email["user"]; // SMTP username
    $mail->Password=$email["pass"]; // SMTP password
    $mail->SMTPAuth=true; // Enable SMTP authentication
    $mail->SMTPSecure="ssl"; // Enable SSL
    $mail->Port=465; // TCP port to connect to
    $mail->CharSet="UTF-8";
    $mail->From=$systememail;
    $mail->FromName=$systemname;
    $mail->addAddress($emailto);     // Add a recipient
    $mail->addBCC($systememail);     // Add a recipient
    $mail->Subject=$subject;
    $mail->Body=$message;
    if (DEBUG===false) {
        $mail->send();
    } else {
        echo $email,' | ',$subject,' | ',$message;
    }
}

function sendSMS($number, $text)
{
    global $sms;

    $message=$text;
    if (strlen($message)>160) {
        $message=chunk_split($message, 160, "|");
        $message=explode("|", $message);
        foreach ($message as $text) {
            $text=trim($text);
            if ($text) {
                log_sendsms($number, $text);
                if (DEBUG===true) {
                    echo $number,' -&gt ',$text,'<br />';
                } else {
                    $sms->Send($number, $text);
                }
            }
        }
    } else {
        log_sendsms($number, $text);
        if (DEBUG===true) {
            echo $number,' -&gt ',$text,'<br />';
        } else {
            $sms->Send($number, $text);
        }
    }

}

function log_sendsms($number, $text)
{
    R::selectDatabase('localdb');
    R::begin();
    $logger=R::dispense('sent');
    $logger->number=$number;
    $logger->text=$text;
    R::store($logger);
    R::commit();
    R::selectDatabase('default');
}

function generatecodes($numcodes, $codelength, $wastage = 25)
{
   // exclude problem chars: B8G6I1l0OQDS5Z2
   // acceptable characters:
    $goodchars='ACEFHJKMNPRTUVWXY4937';
   // build array allowing for possible wastage through duplicate values
    for ($i=0; $i<=$numcodes+$wastage+1; $i++) {
        $codes[]=substr(str_shuffle($goodchars), 0, $codelength);
    }
    return array_slice($codes, 0, ($numcodes+1));
}

function getprivileges($userid)
{
    $user = R::load('users', $userid);
    if ($user->id) {
        return $user->privileges;
    }
    return false;
}

function getusername($userid)
{
    $user=R::load('users', $userid);
    if ($user->id) {
        return $user->username;
    }
    return false;
}

function getphonenumber($userid)
{
    $user=R::load('users', $userid);
    if ($user->id) {
        return $user->number;
    }
    return false;
}

function getuserid($number)
{
    $user=R::findOne('users', 'number=?', [$number]);
    if (!empty($user)) {
        return $user->id;
    }
    return false;
}

function isloggedin()
{
    if (isset($_COOKIE["loguserid"]) and isset($_COOKIE["logsession"])) {
        $session = R::findOne('sessions', 'userid=:userid AND sessionid=:sessionid AND timestamp>:timestamp',
                              [':userid'=>$_COOKIE["loguserid"],':sessionid'=>$_COOKIE["logsession"],':timestamp'=>time()]);
        if (!empty($session)) {
            return 1;
        } else {
            return 0;
        }
    }
    return 0;
}

function checksession()
{
    global $systemURL;
    $sessions = R::find('sessions', 'timestamp<=?', [time()]);
    R::trashAll($sessions);

    if (isset($_COOKIE["loguserid"]) and isset($_COOKIE["logsession"])) {
        $session = R::findOne('sessions', 'userid=:userid AND sessionid=:sessionid AND timestamp>:timestamp', [':userid'=>$_COOKIE["loguserid"],':sessionid'=>$_COOKIE["logsession"],':timestamp'=>time()]);
        if (!empty($session)) {
            $session->timestamp = time()+86400*14;
            R::store($session);
            R::commit();
            R::begin();
        } else {
            $sessions = R::find('sessions', 'userid=:userid OR sessionid=:sessionid', [':userid'=>$_COOKIE["loguserid"],':sessionid'=>$_COOKIE["logsession"]]);
            R::trashAll($sessions);
            R::commit();
            setcookie("loguserid", "", time()-86400);
            setcookie("logsession", "", time()-86400);
            header("HTTP/1.1 301 Moved permanently");
            header("Location: ".$systemURL."?error=2&time=".time());
            header("Connection: close");
            exit;
        }
    } else {
        header("HTTP/1.1 301 Moved permanently");
        header("Location: ".$systemURL."?error=2&time=".time());
        header("Connection: close");
        exit;
    }

}

function checkprivileges($userid)
{
    $privileges = getprivileges($userid);
    if ($privileges < 1) {
        status('PRIVILEGES', 101);
        exit;
    }
}

function logrequest($userid)
{
    R::selectDatabase('localdb');
    R::begin();
    $received=R::dispense('received');
    $received->sender=getphonenumber($userid);
    $received->receivetime=date("Y-m-d H:i:s");
    $received->smstext=$_SERVER['REQUEST_URI'];
    $received->ip=$_SERVER['REMOTE_ADDR'];
    R::store($received);
    R::commit();
    R::selectDatabase('default');
}

function logresult($userid, $text)
{
    R::selectDatabase('localdb');
    R::begin();
    $logtext="";
    if (is_array($text)) {
        foreach ($text as $value) {
            $logtext.=$value."; ";
        }
    } else {
        $logtext=$text;
    }
    $logtext=strip_tags($logtext);
    $sent=R::dispense('sent');
    $sent->number=$userid;
    $sent->text=$logtext;
    R::store($sent);
    R::commit();
    R::selectDatabase('default');
}

function checkbikeno($bikenum)
{
    $bike=R::load('bikes', $bikenum);
    if (!$bike->id) {
        response('<h3>Bike '.$bikenum.' does not exist!</h3>', ERROR);
    }
}

function checkstandname($standname)
{
    $standname=trim(strtoupper($standname));
    $stand=R::findOne('stands', 'standname=?', [$standname]);
    if (empty($stand)) {
        $values->standname=$standname;
        status('CHECKSTAND', 100, $values);
    }
}

/**
 * @param int $notificationtype 0 = via SMS, 1 = via email
**/
function notifyAdmins($message, $notificationtype = 0)
{
    global $systemname,$watches;
    $admins=R::find('users', 'privileges=?', [1]);
    if (!empty($admins)) {
        foreach ($admins as $admin) {
            if ($notificationtype==0) {
                sendSMS($admin->number, $message);
                sendEmail($watches["email"], $systemname." "._('notification'), $message);
            } else {
                sendEmail($admin->mail, $systemname." "._('notification'), $message);
            }
        }
    }
}

function sendConfirmationEmail($emailto)
{

    global $dbpassword, $systemname, $systemrules, $systemURL;

    $subject=_('Registration');
    $user=R::findOne('users', 'mail=?', [$emailto]);
    $registration=R::dispense('registration');
    $registration->userid=$user->id;
    $registration->userkey=hash('sha256', $emailto.$dbpassword.rand(0, 1000000));
    R::store($registration);
    $limit=R::dispense('limits');
    $limit->userid=$user->id;
    $limit->userlimit=0;
    R::store($limit);
    $credit=R::dispense('credit');
    $credit->userid=$user->id;
    $credit->credit=0;
    R::store($credit);

    $names=preg_split("/[\s,]+/", $user->username);
    $firstname=$names[0];
    $message=_('Hello').' '.$firstname.",\n\n".
    _('you have been registered into community bike share system').' '.$systemname.".\n\n".
    _('System rules are available here:')."\n".$systemrules."\n\n".
    _('By clicking the following link you agree to the System rules:')."\n".$systemURL."agree.php?key=".$registration->userkey;
    sendEmail($emailto, $subject, $message);
}

function confirmUser($userkey)
{
    global $limits;

    $user=R::findOne('registration', 'userkey=?', [$userkey]);
    if (!empty($user)) {
        $limit=R::findOne('limits', 'userid=?', [$user->id]);
        $limit->userlimit=$limits["registration"];
        R::store($limit);
        $registration=R::findOne('registration', 'userid=?', [$user->id]);
        R::trash($registration);
        echo '<div class="alert alert-success" role="alert">',_('Your account has been activated. Welcome!'),'</div>';
    } else {
        echo '<div class="alert alert-danger" role="alert">',_('Registration key not found!'),'</div>';
        return false;
    }
}

function checktopofstack($standid)
{
    $currentbikes=array();
   // find current bikes at stand
    $rows=R::getAll('SELECT * FROM bikes LEFT JOIN stands ON bikes.currentstand=stands.id WHERE stands.id=?', [$standid]);
    $bikes=R::convertToBeans('bikes', $rows);
    if (!empty($bikes)) {
        foreach ($bikes as $bike) {
            $currentbikes[]=$bike->bikenum;
        }
        if (count($currentbikes)) {
         // find last returned bike at stand
            $history=R::findOne('history', 'FIND_IN_SET(action,:action) AND parameter=:standid AND FIND_IN_SET(bikenum,:currentbikes) ORDER BY time DESC LIMIT 1', [':action'=>'RETURN,FORCERETURN',':standid'=>$standid,':currentbikes'=>implode($currentbikes, ",")]);
            if (!empty($history)) {
                return $history->bikenum;
            }
        }
    }
    return false;
}

function checklongrental()
{
    global $watches,$notifyuser;

    $abusers="";
    $found=0;
    $rows=R::getAll('SELECT * FROM bikes LEFT JOIN users ON bikes.currentuser=users.id WHERE currentstand IS NULL');
    $bikes=R::convertToBeans('bikes', $rows);
    if (!empty($bikes)) {
        foreach ($bikes as $bike) {
            $history=R::find('history', 'bikenum=:bikenum AND userid=$userid AND action=:action ORDER BY time DESC LIMIT 1', [':bikenum'=>$bike->bikenum,':userid'=>$bike->currentuser,':action'=>'RENT']);
            if (!empty($history)) {
                foreach ($history as $historyitem) {
                    $time=strtotime($historyitem->time);
                    if ($time+($watches["longrental"]*3600)<=time()) {
                        $abusers.=" b".$bike->bikenum." "._('by')." ".$bike->username.",";
                        $found=1;
                        if ($notifyuser) {
                            sendSMS($bike->number, _('Please, return your bike ').$bike->bikenum._(' immediately to the closest stand! Ignoring this warning can get you banned from the system.'));
                        }
                    }
                }
            }
        }

    }
    if ($found) {
        $abusers=substr($abusers, 0, strlen($abusers)-1);
        notifyAdmins($watches["longrental"]."+ "._('hour rental').":".$abusers);
    }

}

// cron - called from cron by default, set to 0 if from rent function, userid needs to be passed if cron=0
function checktoomany($cron = 1, $userid = 0)
{
    global $watches;

    $abusers="";
    $found=0;
    if ($cron) { // called from cron
        $rows=R::getAll('SELECT * FROM users LEFT JOIN limits ON users.id=limits.userid');
        $users=R::convertToBeans('users', $rows);
        if (!empty($users)) {
            foreach ($users as $user) {
                $numberofrentals=R::count('history', 'userid=:userid AND action=:action AND time>:time', [':userid'=>$user->id,':action'=>'RENT','time'=>date("Y-m-d H:i:s", time()-$watches["timetoomany"]*3600)]);
                if ($numberofrentals>=($user->userlimit+$watches["numbertoomany"])) {
                    $abusers.=" ".$numberofrentals." ("._('limit')." ".$user->userlimit.") "._('by')." ".$user->username.",";
                    $found=1;
                }
            }
        }
    } else // called from function for user userid
      {
        $rows=R::getAll('SELECT * FROM users LEFT JOIN limits ON users.id=limits.userid WHERE users.id=?', [$userid]);
        $users=R::convertToBeans('users', $rows);
        if (!empty($users)) {
            foreach ($users as $user) {
                $numberofrentals=R::count('history', 'userid=:userid AND action=:action AND time>:time', [':userid'=>$user->id,':action'=>'RENT','time'=>date("Y-m-d H:i:s", time()-$watches["timetoomany"]*3600)]);
                if ($numberofrentals>=($user->userlimit+$watches["numbertoomany"])) {
                    $abusers.=" ".$numberofrentals." ("._('limit')." ".$user->userlimit.") "._('by')." ".$user->username.",";
                    $found=1;
                }
            }
        }
    }
    if ($found) {
        $abusers=substr($abusers, 0, strlen($abusers)-1);
        notifyAdmins(_('Over limit in')." ".$watches["timetoomany"]." "._('hs').":".$abusers);
    }

}

// check if user has credit >= minimum credit+rent fee+long rental fee
function getrequiredcredit()
{
    global $credit;

    if (iscreditenabled()==false) {
        return; // if credit system disabled, exit
    }
    $requiredcredit=$credit["min"]+$credit["rent"]+$credit["longrental"];
    return $requiredcredit;

}

// check if user has credit >= minimum credit+rent fee+long rental fee
function checkrequiredcredit($userid)
{
    global $credit;

    if (iscreditenabled()==false) {
        return; // if credit system disabled, exit
    }
    $requiredcredit=getrequiredcredit();
    $credit=R::findOne('credit', 'userid=:userid AND credit>=:requiredcredit', [':userid'=>$userid,':requiredcredit'=>$requiredcredit]);
    if (!empty($credit)) {
        return true;
    }
    return false;

}

// subtract credit for rental
function changecreditendrental($bikenum, $userid)
{
    global $watches,$credit;

    if (iscreditenabled()==false) {
        return; // if credit system disabled, exit
    }
    $usercredit=getusercredit($userid);

    $history=R::findOne('history', 'bikenum=:bikenum AND userid=:userid AND FIND_IN_SET(action,:action) ORDER BY time DESC LIMIT 1', [':bikenum'=>$bikenum,':userid'=>$userid,':action'=>'RENT,FORCERENT']);
    if (!empty($history)) {
        $starttime=strtotime($history->time);
        $endtime=time();
        $timediff=$endtime-$starttime;
        $creditchange=0;
        $changelog="";
        if ($timediff>$watches["freetime"]*60) {
            $creditchange=$creditchange+$credit["rent"];
            $changelog.="overfree-".$credit["rent"].";";
        }
        if ($watches["freetime"]==0) {
            $watches["freetime"]=1; // for further calculations
        }       if ($credit["pricecycle"] and $timediff>$watches["freetime"]*60*2) { // after first paid period, i.e. freetime*2; if pricecycle enabled
            $temptimediff=$timediff-($watches["freetime"]*60*2);
            if ($credit["pricecycle"]==1) { // flat price per cycle
                $cycles=ceil($temptimediff/($watches["flatpricecycle"]*60));
                $creditchange=$creditchange+($credit["rent"]*$cycles);
                     $changelog.="flat-".$credit["rent"]*$cycles.";";
            } elseif ($credit["pricecycle"]==2) { // double price per cycle
                    $cycles=ceil($temptimediff/($watches["doublepricecycle"]*60));
                $tempcreditrent=$credit["rent"];
                for ($i=1; $i<=$cycles; $i++) {
                    $multiplier=$i;
                    if ($multiplier>$watches["doublepricecyclecap"]) {
                        $multiplier=$watches["doublepricecyclecap"];
                    }
                   // exception for rent=1, otherwise square won't work:
                    if ($tempcreditrent==1) {
                        $tempcreditrent=2;
                    }
                    $creditchange=$creditchange+pow($tempcreditrent, $multiplier);
                    $changelog.="double-".pow($tempcreditrent, $multiplier).";";
                }
            }
        }
        if ($timediff>$watches["longrental"]*3600) {
            $creditchange=$creditchange+$credit["longrental"];
            $changelog.="longrent-".$credit["longrental"].";";
        }
        $usercredit=$usercredit-$creditchange;
        $credit=R::findOne('credit', 'userid=?', [$userid]);
        $credit->credit=$usercredit;
        R::store($credit);
        $history=R::dispense('history');
        $history->userid=$userid;
        $history->bikenum=$bikenum;
        $history->action='CREDITCHANGE';
        $history->parameter=$creditchange.'|'.$changelog;
        R::store($history);
        $history=R::dispense('history');
        $history->userid=$userid;
        $history->bikenum=$bikenum;
        $history->action='CREDIT';
        $history->parameter=$usercredit;
        R::store($history);
        return $creditchange;
    }

}

function iscreditenabled()
{
    global $credit;

    if ($credit["enabled"]) {
        return true;
    }

    return false;

}

function getusercredit($userid)
{

    if (iscreditenabled()==false) {
        return; // if credit system disabled, exit
    }
    $credit=R::findOne('credit', 'userid=?', [$userid]);

    return $credit->credit;

}

function getcreditcurrency()
{
    global $credit;

    if (iscreditenabled()==false) {
        return; // if credit system disabled, exit
    }
    return $credit["currency"];

}

function issmssystemenabled()
{
    global $connectors;

    if ($connectors["sms"]=="") {
        return false;
    }

    return true;

}


function normalizephonenumber($number)
{
    global $countrycode;
    $number=str_replace("+", "", $number);
    $number=str_replace(" ", "", $number);
    $number=str_replace("-", "", $number);
    $number=str_replace("/", "", $number);
    $number=str_replace(".", "", $number);
    if (substr($number, 0, 1)=="0") {
        $number=substr($number, 1);
    }
    if (substr($number, 0, 3)<>$countrycode) {
        $number=$countrycode.$number;
    }
    return $number;
}

function log_queries()
{
    $logs = R::getDatabaseAdapter()
            ->getDatabase()
            ->getLogger();
    error_log(print_r($logs, true));
}
