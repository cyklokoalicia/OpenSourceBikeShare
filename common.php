<?php

require_once 'vendor/autoload.php';

use BikeShare\App\Configuration;
use BikeShare\App\Kernel;
use BikeShare\Authentication\Auth;
use BikeShare\Credit\CodeGenerator\CodeGeneratorInterface;
use BikeShare\Credit\CreditSystemInterface;
use BikeShare\Db\DbInterface;
use BikeShare\Mail\MailSenderInterface;
use BikeShare\Purifier\PhonePurifierInterface;
use BikeShare\Rent\RentSystemFactory;
use BikeShare\Repository\StandRepository;
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
       $standRepository,
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
$standRepository = $kernel->getContainer()->get(StandRepository::class);

$locale = $configuration->get('systemlang') . ".utf8";
setlocale(LC_ALL, $locale);
putenv("LANG=" . $locale);
bindtextdomain("messages", dirname(__FILE__) . '/languages');
textdomain("messages");

function logrequest($userid)
{
    global $user, $db;

    $number = $user->findPhoneNumber($userid);

    $db->query("INSERT INTO received SET sms_uuid='', sender='$number',receive_time='" . date('Y-m-d H:i:s') . "',sms_text='" . $_SERVER['REQUEST_URI'] . "',ip='" . $_SERVER['REMOTE_ADDR'] . "'");
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