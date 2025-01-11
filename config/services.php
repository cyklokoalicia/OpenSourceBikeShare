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
use BikeShare\Db\PdoDb;
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
    $services->defaults()
        ->public()
        ->autoconfigure()
        ->autowire()
        ->bind('$isSmsSystemEnabled', expr("container.getEnv('SMS_CONNECTOR') ? true : false"))
        ->bind('$appName', env('APP_NAME'));

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
            '../src/Db/*DbResult.php',
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
        ]);
    $services->get(PdoDb::class)
        ->args([
            env('DB_DSN'),
            env('DB_USER'),
            env('DB_PASSWORD'),
            service('logger')
        ]);

    $services->alias(DbInterface::class, PdoDb::class);

    $services->get(\BikeShare\Mail\MailSenderFactory::class)
        ->bind('$smtpHost', env('SMTP_HOST'));
    $services->get(PHPMailerMailSender::class)
        ->bind('$fromEmail', env('SMTP_FROM_EMAIL'))
        ->bind('$fromName', env('APP_NAME'))
        ->bind('$emailConfig', [
            'smtp_host' => env('SMTP_HOST'),
            'smtp_port' => env('int:SMTP_PORT'),
            'smtp_user' => env('SMTP_USER'),
            'smtp_password' => env('SMTP_PASSWORD'),
        ])
        ->bind('$debugLevel', env('int:SMTP_DEBUG_LEVEL'))
        ->bind(
            '$mailer',
            inline_service(PHPMailer::class)->args([false])->property('Debugoutput', service('logger')),
        );

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
        ->bind('$forceStack', expr("service('BikeShare\\\App\\\Configuration').get('forceStack')"));

    $services->load('BikeShare\\SmsConnector\\', '../src/SmsConnector')
        ->bind('$request', expr("service('request_stack').getCurrentRequest()"))
        ->bind('$configuration', env('json:SMS_CONNECTOR_CONFIG'));
    $services->get(SmsConnectorFactory::class)
        ->arg('$connectorName', env('SMS_CONNECTOR'));

    $services->alias(SmsSenderInterface::class, SmsSender::class);

    $services->alias(CodeGeneratorInterface::class, CodeGenerator::class);

    $services->alias(PhonePurifierInterface::class, PhonePurifier::class);

    $services->load('BikeShare\\EventListener\\', '../src/EventListener')
        ->tag('kernel.event_listener');
};
