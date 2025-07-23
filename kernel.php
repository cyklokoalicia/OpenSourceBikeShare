<?php

defined('ERROR') ? '': define('ERROR', 1);

use BikeShare\App\Kernel;
use BikeShare\Authentication\Auth;
use BikeShare\Credit\CodeGenerator\CodeGeneratorInterface;
use BikeShare\Credit\CreditSystemInterface;
use BikeShare\Db\DbInterface;
use BikeShare\Mail\MailSenderInterface;
use BikeShare\Purifier\PhonePurifierInterface;
use BikeShare\Rent\RentSystemFactory;
use BikeShare\Repository\CityRepository;
use BikeShare\Repository\StandRepository;
use BikeShare\Sms\SmsSenderInterface;
use BikeShare\SmsConnector\SmsConnectorInterface;
use BikeShare\User\User;
use Symfony\Component\Dotenv\Dotenv;

/**
 * should be removed
 */
global $sms,
       $db,
       $requestStack,
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
       $cityRepository,
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
$sms = $kernel->getContainer()->get(SmsConnectorInterface::class);
$db = $kernel->getContainer()->get(DbInterface::class);
$requestStack = $kernel->getContainer()->get('request_stack');
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
$cityRepository = $kernel->getContainer()->get(CityRepository::class);

$locale = $kernel->getContainer()->getParameter('kernel.default_locale') . ".utf8";
setlocale(LC_ALL, $locale);
putenv("LANG=" . $locale);
bindtextdomain("messages", dirname(__FILE__) . '/languages');
textdomain("messages");