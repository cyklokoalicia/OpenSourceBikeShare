<?php

use BikeShare\Credit\CodeGenerator\CodeGenerator;
use BikeShare\Credit\CodeGenerator\CodeGeneratorInterface;
use BikeShare\Credit\CreditSystemFactory;
use BikeShare\Credit\CreditSystemInterface;
use BikeShare\Mail\DebugMailSender;
use BikeShare\Mail\MailSenderInterface;
use BikeShare\Mail\PHPMailerMailSender;
use BikeShare\Db\DbInterface;
use BikeShare\Db\MysqliDb;
use BikeShare\Purifier\PhonePurifier;
use BikeShare\Purifier\PhonePurifierInterface;
use BikeShare\Sms\SmsSender;
use BikeShare\Sms\SmsSenderInterface;
use BikeShare\SmsConnector\DebugConnector;
use BikeShare\SmsConnector\SmsConnectorFactory;
use BikeShare\User\User;
use Monolog\ErrorHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

require_once 'vendor/autoload.php';

$logger = new Logger('BikeShare');
$logger->pushHandler(new RotatingFileHandler( __DIR__ . '/var/log/log.log', 30, Logger::WARNING));
ErrorHandler::register($logger);

$locale = $systemlang . ".utf8";
setlocale(LC_ALL, $locale);
putenv("LANG=" . $locale);
bindtextdomain("messages", dirname(__FILE__) . '/languages');
textdomain("messages");

$sms = (new SmsConnectorFactory($logger))->getConnector(
    !empty($connectors["sms"]) ? $connectors["sms"] : 'disabled',
    !empty($connectors["config"][$connectors["sms"]]) ? json_decode($connectors["config"][$connectors["sms"]], true) : array(),
    DEBUG
);

/**
 * @var DbInterface $db
 */
$db = new MysqliDb($dbserver, $dbuser, $dbpassword, $dbname, $logger);
$db->connect();

/**
 * @var MailSenderInterface $mailer
 */
if (DEBUG === TRUE) {
    $mailer = new DebugMailSender();
} else {
    $mailer = new PHPMailerMailSender(
        $systemname,
        $systememail,
        $email,
        new PHPMailer(false)
    );
}

/**
 * @var SmsSenderInterface $smsSender
 */
$smsSender = new SmsSender(
    DEBUG === TRUE ? new DebugConnector() : $sms,
    $db
);

/**
 * @var CodeGeneratorInterface $codeGenerator
 */
$codeGenerator = new CodeGenerator();

$user = new User($db);

/**
 * @var PhonePurifierInterface $phonePurifier
 */
$phonePurifier = new PhonePurifier($countrycode);

/**
 * @var CreditSystemInterface $creditSystem
 */
$creditSystem = (new CreditSystemFactory())->getCreditSystem($credit, $db);

function error($message)
{
   global $db;
   $db->rollback();
   exit($message);
}

function logrequest($userid)
{
   global $dbserver,$dbuser,$dbpassword,$dbname, $user, $logger;
    /**
     * @var DbInterface
     */
    $localdb = new MysqliDb($dbserver, $dbuser, $dbpassword, $dbname, $logger);
    $localdb->connect();

    #TODO does it needed???
    $localdb->setAutocommit(true);

    $number = $user->findPhoneNumber($userid);

    $localdb->query("INSERT INTO received SET sms_uuid='', sender='$number',receive_time='" . date('Y-m-d H:i:s') . "',sms_text='" . $_SERVER['REQUEST_URI'] . "',ip='" . $_SERVER['REMOTE_ADDR'] . "'");
}

function logresult($userid, $text)
{
    global $dbserver, $dbuser, $dbpassword, $dbname, $logger;

    /**
     * @var DbInterface
     */
    $localdb = new MysqliDb($dbserver, $dbuser, $dbpassword, $dbname, $logger);
    $localdb->connect();

    #TODO does it needed???
    $localdb->setAutocommit(true);
   $userid = $localdb->escape($userid);
   $logtext="";
   if (is_array($text))
      {
      foreach ($text as $value)
         {
         $logtext.=$value."; ";
         }
      }
   else
      {
      $logtext=$text;
      }

    $logtext = strip_tags($localdb->escape($logtext));

    $result = $localdb->query("INSERT INTO sent SET number='$userid',text='$logtext'");
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
    $result = $db->query("SELECT standName FROM stands WHERE standName='$stand'");
    if (!$result->num_rows) {
        response('<h3>' . _('Stand') . ' ' . $stand . ' ' . _('does not exist') . '!</h3>', ERROR);
    }
}

/**
 * @param int $notificationtype 0 = via SMS, 1 = via email
 **/
function notifyAdmins($message, $notificationtype = 0)
{
    global $db, $systemname, $watches, $mailer, $smsSender;

    $result = $db->query('SELECT number,mail FROM users where privileges & 2 != 0');
    while ($row = $result->fetch_assoc()) {
        if ($notificationtype == 0) {
            $smsSender->send($row['number'], $message);
            $mailer->sendMail($watches['email'], $systemname . ' ' . _('notification'), $message);
        } else {
            $mailer->sendMail($row['mail'], $systemname . ' ' . _('notification'), $message);
        }
    }//copy to Trello board -- might be added as a person instead
    if ($notificationtype == 0) {
        $mailer->sendMail('cyklokoalicia1+q31wfjphbgkuelf19hlb@boards.trello.com', $message, $message);
    }
}

function sendConfirmationEmail($emailto)
{

    global $db, $dbpassword, $systemname, $systemrules, $systemURL, $mailer;

    $subject = _('Registration');

    $result = $db->query("SELECT userName,userId FROM users WHERE mail='" . $emailto . "'");
    $row = $result->fetch_assoc();

    $userId = $row['userId'];
    $userKey = hash('sha256', $emailto . $dbpassword . rand(0, 1000000));

    $db->query("INSERT INTO registration SET userKey='$userKey',userId='$userId'");
    $db->query("INSERT INTO limits SET userId='$userId',userLimit=0");
    $db->query("INSERT INTO credit SET userId='$userId',credit=0");

    $names = preg_split("/[\s,]+/", $row['userName']);
    $firstname = $names[0];
    $message = _('Hello') . ' ' . $firstname . ",\n\n" .
        _('you have been registered into community bike share system') . ' ' . $systemname . ".\n\n" .
        _('System rules are available here:') . "\n" . $systemrules . "\n\n" .
        _('By clicking the following link you agree to the System rules:') . "\n" . $systemURL . 'agree.php?key=' . $userKey;
    $mailer->sendMail($emailto, $subject, $message);
}

function confirmUser($userKey)
{
    global $db, $limits;
    $userKey = $db->escape($userKey);

    $result = $db->query("SELECT userId FROM registration WHERE userKey='$userKey'");
    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $userId = $row['userId'];
    } else {
        echo '<div class="alert alert-danger" role="alert">', _('Registration key not found!'), '</div>';
        return false;
    }

    $db->query("UPDATE limits SET userLimit='" . $limits['registration'] . "' WHERE userId=$userId");

    $db->query("DELETE FROM registration WHERE userId='$userId'");

    echo '<div class="alert alert-success" role="alert">', _('Your account has been activated. Welcome!'), '</div>';
}

function checktopofstack($standid)
{
    global $db;
    $currentbikes = array();
    // find current bikes at stand
    $result = $db->query("SELECT bikeNum FROM bikes LEFT JOIN stands ON bikes.currentStand=stands.standId WHERE standId='$standid'");
    while ($row = $result->fetch_assoc()) {
        $currentbikes[] = $row['bikeNum'];
    }
    if (count($currentbikes)) {
        // find last returned bike at stand
        $result = $db->query("SELECT bikeNum FROM history WHERE action IN ('RETURN','FORCERETURN') AND parameter='$standid' AND bikeNum IN (" . implode($currentbikes, ',') . ') ORDER BY time DESC LIMIT 1');
        if ($result->num_rows) {
            $row = $result->fetch_assoc();
            return $row['bikeNum'];
        }
    }
    return false;
}

function checklongrental()
{
    global $db, $smsSender, $watches, $notifyuser;

    $abusers = '';
    $found = 0;
    $result = $db->query('SELECT bikeNum,currentUser,userName,number FROM bikes LEFT JOIN users ON bikes.currentUser=users.userId WHERE currentStand IS NULL');
    while ($row = $result->fetch_assoc()) {
        $bikenum = $row['bikeNum'];
        $userid = $row['currentUser'];
        $username = $row['userName'];
        $userphone = $row['number'];
        $result2 = $db->query("SELECT time FROM history WHERE bikeNum=$bikenum AND userId=$userid AND action='RENT' ORDER BY time DESC LIMIT 1");
        if ($result2->num_rows) {
            $row2 = $result2->fetch_assoc();
            $time = $row2['time'];
            $time = strtotime($time);
            if ($time + ($watches['longrental'] * 3600) <= time()) {
                $abusers .= ' b' . $bikenum . ' ' . _('by') . ' ' . $username . ',';
                $found = 1;
                if ($notifyuser) {
                    $smsSender->send($userphone, _('Please, return your bike ') . $bikenum . _(' immediately to the closest stand! Ignoring this warning can get you banned from the system.'));
                }
            }
        }
    }
    if ($found) {
        $abusers = substr($abusers, 0, strlen($abusers) - 1);
        notifyAdmins($watches['longrental'] . '+ ' . _('hour rental') . ':' . $abusers);
    }
}

// cron - called from cron by default, set to 0 if from rent function, userid needs to be passed if cron=0
function checktoomany($cron = 1, $userid = 0)
{
    global $db, $watches;

    $abusers = '';
    $found = 0;

    if ($cron) { // called from cron
        $result = $db->query('SELECT users.userId,userName,userLimit FROM users LEFT JOIN limits ON users.userId=limits.userId');
        while ($row = $result->fetch_assoc()) {
            $userid = $row['userId'];
            $username = $row['userName'];
            $userlimit = $row['userLimit'];
            $currenttime = date('Y-m-d H:i:s', time() - $watches['timetoomany'] * 3600);
            $result2 = $db->query("SELECT bikeNum FROM history WHERE userId=$userid AND action='RENT' AND time>'$currenttime'");
            if ($result2->num_rows >= ($userlimit + $watches['numbertoomany'])) {
                $abusers .= ' ' . $result2->num_rows . ' (' . _('limit') . ' ' . $userlimit . ') ' . _('by') . ' ' . $username . ',';
                $found = 1;
            }
        }
    } else { // called from function for user userid
        $result = $db->query("SELECT users.userId,userName,userLimit FROM users LEFT JOIN limits ON users.userId=limits.userId WHERE users.userId=$userid");
        $row = $result->fetch_assoc();
        $username = $row['userName'];
        $userlimit = $row['userLimit'];
        $currenttime = date('Y-m-d H:i:s', time() - $watches['timetoomany'] * 3600);
        $result = $db->query("SELECT bikeNum FROM history WHERE userId=$userid AND action='RENT' AND time>'$currenttime'");
        if ($result->num_rows >= ($userlimit + $watches['numbertoomany'])) {
            $abusers .= ' ' . $result->num_rows . ' (' . _('limit') . ' ' . $userlimit . ') ' . _('by') . ' ' . $username . ',';
            $found = 1;
        }
    }
    if ($found) {
        $abusers = substr($abusers, 0, strlen($abusers) - 1);
        notifyAdmins(_('Over limit in') . ' ' . $watches['timetoomany'] . ' ' . _('hs') . ':' . $abusers);
    }
}

// subtract credit for rental
function changecreditendrental($bike, $userid)
{
    global $db, $watches, $credit, $creditSystem;

    if ($creditSystem->isEnabled() == false) {
        return;
    }
    // if credit system disabled, exit

    $userCredit = $creditSystem->getUserCredit($userid);

    $result = $db->query("SELECT time FROM history WHERE bikeNum=$bike AND userId=$userid AND (action='RENT' OR action='FORCERENT') ORDER BY time DESC LIMIT 1");
    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $starttime = strtotime($row['time']);
        $endtime = time();
        $timediff = $endtime - $starttime;
        $creditchange = 0;
        $changelog = '';

        //ak vrati a znova pozica bike do 10 min tak free time nebude mať.
		  $oldRetrun = $db->query("SELECT time FROM history WHERE bikeNum=$bike AND userId=$userid AND (action='RETURN' OR action='FORCERETURN') ORDER BY time DESC LIMIT 1");
		  if ($oldRetrun->num_rows==1) {
              $oldRow = $oldRetrun->fetch_assoc();
              $returntime = strtotime($oldRow["time"]);
              if (($starttime - $returntime) < 10 * 60 && $timediff > 5 * 60) {
                  $creditchange = $creditchange + $creditSystem->getRentalFee();
                  $changelog .= 'rerent-' . $creditSystem->getRentalFee() . ';';
              }
		  }
        //end

        if ($timediff > $watches['freetime'] * 60) {
            $creditchange = $creditchange + $creditSystem->getRentalFee();
            $changelog .= 'overfree-' . $creditSystem->getRentalFee() . ';';
        }
        if ($watches['freetime'] == 0) {
            $watches['freetime'] = 1;
        }
        // for further calculations
        if ($creditSystem->getPriceCycle() && $timediff > $watches['freetime'] * 60 * 2) { // after first paid period, i.e. freetime*2; if pricecycle enabled
            $temptimediff = $timediff - ($watches['freetime'] * 60 * 2);
            if ($creditSystem->getPriceCycle() == 1) { // flat price per cycle
                $cycles = ceil($temptimediff / ($watches['flatpricecycle'] * 60));
                $creditchange = $creditchange + ($creditSystem->getRentalFee() * $cycles);
                $changelog .= 'flat-' . $creditSystem->getRentalFee() * $cycles . ';';
            } elseif ($creditSystem->getPriceCycle() == 2) { // double price per cycle
                $cycles = ceil($temptimediff / ($watches['doublepricecycle'] * 60));
                $tempcreditrent = $creditSystem->getRentalFee();
                for ($i = 1; $i <= $cycles; $i++) {
                    $multiplier = $i;
                    if ($multiplier > $watches['doublepricecyclecap']) {
                        $multiplier = $watches['doublepricecyclecap'];
                    }
                    // exception for rent=1, otherwise square won't work:
                    if ($tempcreditrent == 1) {
                        $tempcreditrent = 2;
                    }

                    $creditchange = $creditchange + pow($tempcreditrent, $multiplier);
                    $changelog .= 'double-' . pow($tempcreditrent, $multiplier) . ';';
                }
            }
        }
        if ($timediff > $watches['longrental'] * 3600) {
            $creditchange = $creditchange + $creditSystem->getLongRentalFee();
            $changelog .= 'longrent-' . $creditSystem->getLongRentalFee() . ';';
        }
        $userCredit = $userCredit - $creditchange;
        $db->query("UPDATE credit SET credit=$userCredit WHERE userId=$userid");
        $db->query("INSERT INTO history SET userId=$userid,bikeNum=$bike,action='CREDITCHANGE',parameter='" . $creditchange . '|' . $changelog . "'");
        $db->query("INSERT INTO history SET userId=$userid,bikeNum=$bike,action='CREDIT',parameter=$userCredit");

        return $creditchange;
    }
}

function issmssystemenabled()
{
    global $connectors;

    if ($connectors['sms'] == '') {
        return false;
    }

    return true;
}
