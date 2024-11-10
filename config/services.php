<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use BikeShare\App\Configuration;
use BikeShare\Credit\CodeGenerator\CodeGenerator;
use BikeShare\Credit\CodeGenerator\CodeGeneratorInterface;
use BikeShare\Credit\CreditSystem;
use BikeShare\Credit\CreditSystemFactory;
use BikeShare\Credit\CreditSystemInterface;
use BikeShare\Db\DbInterface;
use BikeShare\Db\MysqliDb;
use BikeShare\Mail\MailSenderInterface;
use BikeShare\Mail\PHPMailerMailSender;
use BikeShare\Purifier\PhonePurifier;
use BikeShare\Purifier\PhonePurifierInterface;
use BikeShare\Rent\RentSystemInterface;
use BikeShare\Sms\SmsSender;
use BikeShare\Sms\SmsSenderInterface;
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

    $services->alias('logger', 'monolog.logger');

    $services->set(DotenvDumpCommand::class)
        ->args([
            param('kernel.project_dir') . '/.env',
            param('kernel.environment'),
        ]);

    $services->set(Configuration::class)
        ->args([__DIR__ . '/../config.php']);

    $services->load('BikeShare\\', '../src/')
        ->exclude([
            '../src/Db/MysqliDbResult.php',
            '../src/SmsConnector/SmsGateway/SmsGateway.php',
            '../src/App/Configuration.php',
            '../src/App/Kernel.php',
            '../src/App/Entity',
        ]);

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
        ->exclude([
            '../src/SmsConnector/SmsGateway/SmsGateway.php'
        ]);
    $services->get(SmsConnectorFactory::class)
        ->arg('$connectorConfig', expr("service('BikeShare\\\App\\\Configuration').get('connectors')"));

    $services->alias(SmsSenderInterface::class, SmsSender::class);

    $services->alias(CodeGeneratorInterface::class, CodeGenerator::class);

    $services->alias(PhonePurifierInterface::class, PhonePurifier::class);
};