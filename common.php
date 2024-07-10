<?php

require_once 'vendor/autoload.php';

use BikeShare\App\Configuration;
use BikeShare\App\Kernel;
use BikeShare\Authentication\Auth;
use BikeShare\Credit\CodeGenerator\CodeGeneratorInterface;
use BikeShare\Credit\CreditSystemInterface;
use BikeShare\Db\DbInterface;
use BikeShare\Db\MysqliDb;
use BikeShare\Mail\MailSenderInterface;
use BikeShare\Purifier\PhonePurifierInterface;
use BikeShare\Rent\RentSystemFactory;
use BikeShare\Sms\SmsSenderInterface;
use BikeShare\SmsConnector\SmsConnectorInterface;
use BikeShare\User\User;
use Symfony\Component\Dotenv\Dotenv;

/**
 * should be removed
 */
global $configuration,
       $sms,
       $db,
       $db,
       $mailer,
       $smsSender,
       $codeGenerator,
       $phonePurifier,
       $creditSystem,
       $user,
       $auth,
       $rentSystemFactory,
       $translator,
       $logger;

if (empty($kernel)) {
    $dotenv = new Dotenv();
    $dotenv->setProdEnvs(['prod']);
    $dotenv->bootEnv(__DIR__.'/.env', 'dev', ['test'], true);

    $kernel = new Kernel($_ENV['APP_ENV'], (bool) $_ENV['APP_DEBUG']);
    $kernel->boot();
    $container = $kernel->getContainer();


    $logger = $kernel->getContainer()->get('logger');
    Monolog\ErrorHandler::register($logger);
    #Should be removed in the future. Currently, we are using it to log errors in old code
    set_error_handler(
        function ($severity, $message, $file, $line) use ($logger) {
            $logger->error($message, ['severity' => $severity, 'file' => $file, 'line' => $line]);

            return true;
        }
    );
}

$logger = $kernel->getContainer()->get('logger');
$configuration = $kernel->getContainer()->get(Configuration::class);
$sms = $kernel->getContainer()->get(SmsConnectorInterface::class);
$db = $kernel->getContainer()->get(DbInterface::class);
$db = $kernel->getContainer()->get(DbInterface::class);
$mailer = $kernel->getContainer()->get(MailSenderInterface::class);
$smsSender = $kernel->getContainer()->get(SmsSenderInterface::class);
$codeGenerator = $kernel->getContainer()->get(CodeGeneratorInterface::class);
$phonePurifier = $kernel->getContainer()->get(PhonePurifierInterface::class);
$creditSystem = $kernel->getContainer()->get(CreditSystemInterface::class);
$user = $kernel->getContainer()->get(User::class);
$auth = $kernel->getContainer()->get(Auth::class);
$rentSystemFactory = $kernel->getContainer()->get(RentSystemFactory::class);
$translator = $kernel->getContainer()->get('translator');

$locale = $configuration->get('systemlang') . ".utf8";
setlocale(LC_ALL, $locale);
putenv("LANG=" . $locale);
bindtextdomain("messages", dirname(__FILE__) . '/languages');
textdomain("messages");

function t(?string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
{
    global $translator;

    return $translator->trans($id, $parameters, $domain, $locale);
}

function error($message)
{
   global $db;
   $db->rollback();
   exit($message);
}

function logrequest($userid)
{
   global $configuration, $user, $logger;
    /**
     * @var DbInterface
     */
    $localdb = new MysqliDb(
        $configuration->get('dbserver'),
        $configuration->get('dbuser'),
        $configuration->get('dbpassword'),
        $configuration->get('dbname'),
        $logger
    );

    #TODO does it needed???
    $localdb->setAutocommit(true);

    $number = $user->findPhoneNumber($userid);

    $localdb->query("INSERT INTO received SET sms_uuid='', sender='$number',receive_time='" . date('Y-m-d H:i:s') . "',sms_text='" . $_SERVER['REQUEST_URI'] . "',ip='" . $_SERVER['REMOTE_ADDR'] . "'");
}

function logresult($userid, $text)
{
    global $configuration, $logger;

    /**
     * @var DbInterface
     */
    $localdb = new MysqliDb(
        $configuration->get('dbserver'),
        $configuration->get('dbuser'),
        $configuration->get('dbpassword'),
        $configuration->get('dbname'),
        $logger
    );

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

    $logtext = substr(strip_tags($localdb->escape($logtext)), 0, 200);

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
    global $db, $configuration, $mailer, $smsSender;

    $result = $db->query('SELECT number,mail FROM users where privileges & 2 != 0');
    while ($row = $result->fetch_assoc()) {
        if ($notificationtype == 0) {
            $smsSender->send($row['number'], $message);
            $mailer->sendMail($configuration->get('watches')['email'], $configuration->get('systemname') . ' ' . _('notification'), $message);
        } else {
            $mailer->sendMail($row['mail'], $configuration->get('systemname') . ' ' . _('notification'), $message);
        }
    }
}

function sendConfirmationEmail($emailto)
{

    global $db, $configuration, $mailer;

    $subject = _('Registration');

    $result = $db->query("SELECT userName,userId FROM users WHERE mail='" . $emailto . "'");
    $row = $result->fetch_assoc();

    $userId = $row['userId'];
    $userKey = hash('sha256', $emailto . $configuration->get('dbpassword') . rand(0, 1000000));

    $db->query("INSERT INTO registration SET userKey='$userKey',userId='$userId'");
    $db->query("INSERT INTO limits SET userId='$userId',userLimit=0");
    $db->query("INSERT INTO credit SET userId='$userId',credit=0");

    $names = preg_split("/[\s,]+/", $row['userName']);
    $firstname = $names[0];
    $message = _('Hello') . ' ' . $firstname . ",\n\n" .
        _('you have been registered into community bike share system') . ' ' . $configuration->get('systemname') . ".\n\n" .
        _('System rules are available here:') . "\n" . $configuration->get('systemrules') . "\n\n" .
        _('By clicking the following link you agree to the System rules:') . "\n" . $configuration->get('systemURL') . 'agree.php?key=' . $userKey;
    $mailer->sendMail($emailto, $subject, $message);
}

function confirmUser($userKey)
{
    global $db, $configuration;
    $userKey = $db->escape($userKey);

    $result = $db->query("SELECT userId FROM registration WHERE userKey='$userKey'");
    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $userId = $row['userId'];
    } else {
        echo '<div class="alert alert-danger" role="alert">', _('Registration key not found!'), '</div>';
        return false;
    }

    $db->query("UPDATE limits SET userLimit='" . $configuration->get('limits')['registration'] . "' WHERE userId=$userId");

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
        $result = $db->query("SELECT bikeNum FROM history WHERE action IN ('RETURN','FORCERETURN') AND parameter='$standid' AND bikeNum IN (" . implode(',', $currentbikes) . ') ORDER BY time DESC LIMIT 1');
        if ($result->num_rows) {
            $row = $result->fetch_assoc();
            return $row['bikeNum'];
        }
    }
    return false;
}

function checklongrental()
{
    global $db, $smsSender, $configuration;

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
            if ($time + ($configuration->get('watches')['longrental'] * 3600) <= time()) {
                $abusers .= ' b' . $bikenum . ' ' . _('by') . ' ' . $username . ',';
                $found = 1;
                if ($configuration->get('notifyuser')) {
                    $smsSender->send($userphone, _('Please, return your bike ') . $bikenum . _(' immediately to the closest stand! Ignoring this warning can get you banned from the system.'));
                }
            }
        }
    }
    if ($found) {
        $abusers = substr($abusers, 0, strlen($abusers) - 1);
        notifyAdmins($configuration->get('watches')['longrental'] . '+ ' . _('hour rental') . ':' . $abusers);
    }
}

// cron - called from cron by default, set to 0 if from rent function, userid needs to be passed if cron=0
function checktoomany($cron = 1, $userid = 0)
{
    global $db, $configuration;

    $abusers = '';
    $found = 0;

    if ($cron) { // called from cron
        $result = $db->query('SELECT users.userId,userName,userLimit FROM users LEFT JOIN limits ON users.userId=limits.userId');
        while ($row = $result->fetch_assoc()) {
            $userid = $row['userId'];
            $username = $row['userName'];
            $userlimit = $row['userLimit'];
            $currenttime = date('Y-m-d H:i:s', time() - $configuration->get('watches')['timetoomany'] * 3600);
            $result2 = $db->query("SELECT bikeNum FROM history WHERE userId=$userid AND action='RENT' AND time>'$currenttime'");
            if ($result2->num_rows >= ($userlimit + $configuration->get('watches')['numbertoomany'])) {
                $abusers .= ' ' . $result2->num_rows . ' (' . _('limit') . ' ' . $userlimit . ') ' . _('by') . ' ' . $username . ',';
                $found = 1;
            }
        }
    } else { // called from function for user userid
        $result = $db->query("SELECT users.userId,userName,userLimit FROM users LEFT JOIN limits ON users.userId=limits.userId WHERE users.userId=$userid");
        $row = $result->fetch_assoc();
        $username = $row['userName'];
        $userlimit = $row['userLimit'];
        $currenttime = date('Y-m-d H:i:s', time() - $configuration->get('watches')['timetoomany'] * 3600);
        $result = $db->query("SELECT bikeNum FROM history WHERE userId=$userid AND action='RENT' AND time>'$currenttime'");
        if ($result->num_rows >= ($userlimit + $configuration->get('watches')['numbertoomany'])) {
            $abusers .= ' ' . $result->num_rows . ' (' . _('limit') . ' ' . $userlimit . ') ' . _('by') . ' ' . $username . ',';
            $found = 1;
        }
    }
    if ($found) {
        $abusers = substr($abusers, 0, strlen($abusers) - 1);
        notifyAdmins(_('Over limit in') . ' ' . $configuration->get('watches')['timetoomany'] . ' ' . _('hs') . ':' . $abusers);
    }
}

function issmssystemenabled()
{
    global $configuration;

    if ($configuration->get('connectors')['sms'] == '') {
        return false;
    }

    return true;
}
