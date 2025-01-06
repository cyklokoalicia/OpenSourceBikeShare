<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use BikeShare\App\Configuration;
use BikeShare\App\EventListener\ErrorListener;
use BikeShare\Credit\CodeGenerator\CodeGenerator;
use BikeShare\Credit\CodeGenerator\CodeGeneratorInterface;
use BikeShare\Credit\CreditSystem;
use BikeShare\Credit\CreditSystemFactory;
use BikeShare\Credit\CreditSystemInterface;
use BikeShare\Db\DbInterface;
use BikeShare\Db\MysqliDb;
use BikeShare\Event\BikeRentEvent;
use BikeShare\Event\BikeRevertEvent;
use BikeShare\Event\LongRentEvent;
use BikeShare\Event\ManyRentEvent;
use BikeShare\Event\SmsDuplicateDetectedEvent;
use BikeShare\Event\SmsProcessedEvent;
use BikeShare\EventListener\AdminNotificationEventListener;
use BikeShare\EventListener\BikeRevertEventListener;
use BikeShare\EventListener\TooManyBikeRentEventListener;
use BikeShare\Mail\MailSenderInterface;
use BikeShare\Mail\PHPMailerMailSender;
use BikeShare\Purifier\PhonePurifier;
use BikeShare\Purifier\PhonePurifierInterface;
use BikeShare\Rent\RentSystemInterface;
use BikeShare\Sms\SmsSender;
use BikeShare\Sms\SmsSenderInterface;
use BikeShare\SmsCommand\SmsCommandInterface;
use BikeShare\SmsConnector\SmsConnectorFactory;
use BikeShare\SmsConnector\SmsConnectorInterface;
use PHPMailer\PHPMailer\PHPMailer;
use Symfony\Component\Dotenv\Command\DotenvDumpCommand;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();
    $services->defaults()->public()->autoconfigure()->autowire();

    $services->instanceof(CreditSystemInterface::class)->tag('creditSystem');
    $services->instanceof(RentSystemInterface::class)->tag('rentSystem');
    $services->instanceof(MailSenderInterface::class)->tag('mailSender');
    $services->instanceof(SmsConnectorInterface::class)->tag('smsConnector');
    $services->instanceof(SmsCommandInterface::class)->tag('smsCommand');

    $services->alias('logger', 'monolog.logger');

    $services->set(DotenvDumpCommand::class)
        ->args([
            param('kernel.project_dir'),
            param('kernel.environment'),
        ]);

    $services->set('exception_listener', ErrorListener::class)
        ->args([
            param('kernel.error_controller'),
            service('logger'),
            param('kernel.debug'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(Configuration::class)
        ->args([__DIR__ . '/../config.php']);

    $services->load('BikeShare\\', '../src/')
        ->exclude([
            '../src/Db/MysqliDbResult.php',
            '../src/SmsConnector/SmsGateway/SmsGateway.php',
            '../src/App/Configuration.php',
            '../src/App/EventListener/ErrorListener.php',
            '../src/App/Kernel.php',
            '../src/App/Entity',
            '../src/Event',
        ]);

    $services->get(\BikeShare\Command\LongRentalCheckCommand::class)
        ->bind('$notifyUser', env('bool:NOTIFY_USER_ABOUT_LONG_RENTAL'));

    $services->get(\BikeShare\Controller\SmsRequestController::class)
        ->bind('$commandLocator', tagged_locator('smsCommand', null, 'getName'));

    $services->get(\BikeShare\SmsCommand\CommandExecutor::class)
        ->bind('$commandLocator', tagged_locator('smsCommand', null, 'getName'));

    $services->get(MysqliDb::class)
        ->args([
            expr("service('BikeShare\\\App\\\Configuration').get('dbserver')"),
            expr("service('BikeShare\\\App\\\Configuration').get('dbuser')"),
            expr("service('BikeShare\\\App\\\Configuration').get('dbpassword')"),
            expr("service('BikeShare\\\App\\\Configuration').get('dbname')"),
            service('logger'),
            false, // throwException
        ]);

    $services->alias(DbInterface::class, MysqliDb::class);

    $services->get(PHPMailerMailSender::class)
        ->args([
            expr("service('BikeShare\\\App\\\Configuration').get('systemname')"),
            expr("service('BikeShare\\\App\\\Configuration').get('systememail')"),
            expr("service('BikeShare\\\App\\\Configuration').get('email')"),
            inline_service( PHPMailer::class)->args([false])
        ]);

    $services->get(PhonePurifier::class)
        ->args([
            expr("service('BikeShare\\\App\\\Configuration').get('countrycode')"),
        ]);

    $services->get(CreditSystemFactory::class)
        ->bind('$creditConfiguration', expr("service('BikeShare\\\App\\\Configuration').get('credit')"));

    $services->get(CreditSystem::class)
        ->bind('$creditConfiguration', expr("service('BikeShare\\\App\\\Configuration').get('credit')"));

    $services->load('BikeShare\\Rent\\', '../src/Rent')
        ->bind('$watchesConfig', expr("service('BikeShare\\\App\\\Configuration').get('watches')"))
        ->bind('$connectorsConfig', expr("service('BikeShare\\\App\\\Configuration').get('connectors')"))
        ->bind('$forceStack', expr("service('BikeShare\\\App\\\Configuration').get('forceStack')"));

    $services->load('BikeShare\\SmsConnector\\', '../src/SmsConnector')
        ->bind('$request', expr("service('request_stack').getCurrentRequest()"))
        ->bind('$configuration', env('json:SMS_CONNECTOR_CONFIG'));
    $services->get(SmsConnectorFactory::class)
        ->arg('$connectorName', env('SMS_CONNECTOR'));

    $services->alias(SmsSenderInterface::class, SmsSender::class);

    $services->alias(CodeGeneratorInterface::class, CodeGenerator::class);

    $services->alias(PhonePurifierInterface::class, PhonePurifier::class);

    $services->get(AdminNotificationEventListener::class)
        ->tag('kernel.event_listener', ['event' => SmsDuplicateDetectedEvent::NAME, 'method' => 'onSmsDuplicateDetected'])
        ->tag('kernel.event_listener', ['event' => SmsProcessedEvent::NAME, 'method' => 'onSmsProcessed'])
        ->tag('kernel.event_listener', ['event' => LongRentEvent::NAME, 'method' => 'onLongRent'])
        ->tag('kernel.event_listener', ['event' => ManyRentEvent::NAME, 'method' => 'onManyRent'])
        ->bind('$appName', env('APP_NAME'));
    $services->get(BikeRevertEventListener::class)
        ->tag('kernel.event_listener', ['event' => BikeRevertEvent::NAME]);
    $services->get(TooManyBikeRentEventListener::class)
        ->tag('kernel.event_listener', ['event' => BikeRentEvent::NAME]);

    $services->load('BikeShare\\EventListener\\', '../src/EventListener')
        ->exclude(
            [
                '../src/EventListener/AdminNotificationEventListener.php',
                '../src/EventListener/BikeRevertEventListener.php',
                '../src/EventListener/TooManyBikeRentEventListener.php',
            ]
        )
        ->tag('kernel.event_listener');
};