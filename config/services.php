<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use BikeShare\App\EventListener\ErrorListener;
use BikeShare\Credit\CodeGenerator\CodeGenerator;
use BikeShare\Credit\CodeGenerator\CodeGeneratorInterface;
use BikeShare\Credit\CreditSystem;
use BikeShare\Credit\CreditSystemFactory;
use BikeShare\Credit\CreditSystemInterface;
use BikeShare\Db\DbInterface;
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
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Dotenv\Command\DotenvDumpCommand;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();
    $services->defaults()
        ->public()
        ->autoconfigure()
        ->autowire()
        ->bind('$isSmsSystemEnabled', expr("container.getEnv('SMS_CONNECTOR') ? true : false"))
        ->bind('$appName', env('APP_NAME'))
        ->bind('$systemRules', env('SYSTEM_RULES'));

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

    $services->load('BikeShare\\', '../src/')
        ->exclude([
            '../src/Db/*DbResult.php',
            '../src/App/Configuration.php',
            '../src/App/EventListener/ErrorListener.php',
            '../src/App/Kernel.php',
            '../src/App/Entity',
            '../src/Event',
            '../src/Command/LoadFixturesCommand.php',
            '../src/SmsCommand/*Command.php',
        ]);

    $services->get(\BikeShare\App\Security\ApiTokenAuthenticator::class)
        ->bind('$validTokens', env('json:SERVICE_API_TOKENS'));

    $services->get(\BikeShare\Command\LongRentalCheckCommand::class)
        ->bind('$notifyUser', env('bool:NOTIFY_USER_ABOUT_LONG_RENTAL'))
        ->bind('$longRentalHours', env('int:WATCHES_LONG_RENTAL'));

    if ($container->env() === 'test') {
        $services->set(\BikeShare\Command\LoadFixturesCommand::class)
            ->bind('$appEnvironment', env('APP_ENV'))
            ->bind('$projectDir', param('kernel.project_dir'))
            ->bind('$dbDatabase', env('DB_DATABASE'))
            ->bind('$fixturesLoader', service('nelmio_alice.file_loader.simple'));
    }

    $services->get(\BikeShare\Controller\SmsRequestController::class)
        ->bind('$commandLocator', tagged_locator('smsCommand', null, 'getName'));

    $services->get(\BikeShare\Controller\HomeController::class)
        ->bind('$freeTimeHours', env('int:WATCHES_FREE_TIME'))
        ->bind('$systemZoom', env('int:SYSTEM_ZOOM'));

    $services->get(\BikeShare\Controller\LanguageController::class)
        ->bind('$enabledLocales', '%kernel.enabled_locales%');

    $services->get(\BikeShare\Controller\EmailConfirmController::class)
        ->bind('$userBikeLimitAfterRegistration', env('int:USER_BIKE_LIMIT_AFTER_REGISTRATION'));

    $services->get(\BikeShare\Controller\Api\StandController::class)
        ->bind('$forceStack', env('bool:FORCE_STACK'));

    $services->get(\BikeShare\SmsCommand\CommandExecutor::class)
        ->bind('$commandLocator', tagged_locator('smsCommand', null, 'getName'));

    $services->load('BikeShare\\SmsCommand\\', '../src/SmsCommand/*Command.php')
        ->bind(RentSystemInterface::class, expr('service("BikeShare\\\Rent\\\RentSystemFactory").getRentSystem("sms")'))
        ->bind('$forceStack', env('bool:FORCE_STACK'))
    ;

    $services->get(PdoDb::class)
        ->args([
            env('resolve:DB_DSN'),
            env('DB_USER'),
            env('DB_PASSWORD'),
        ]);

    $services->alias(DbInterface::class, PdoDb::class);

    $services->get(\BikeShare\Repository\CityRepository::class)
        ->bind('$cities', env('json:CITIES'));

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

    $services->set(PhoneNumberUtil::class)
        ->factory([PhoneNumberUtil::class, 'getInstance']);

    $services->get(PhonePurifier::class)
        ->bind('$countryCodes', env('json:COUNTRY_CODES'));

    $services->get(CreditSystemFactory::class)
        ->bind('$isEnabled', env('bool:CREDIT_SYSTEM_ENABLED'));

    $services->get(CreditSystem::class)
        ->bind('$isEnabled', env('bool:CREDIT_SYSTEM_ENABLED'))
        ->bind('$creditCurrency', env('CREDIT_SYSTEM_CURRENCY'))
        ->bind('$minBalanceCredit', env('float:CREDIT_SYSTEM_MIN_BALANCE'))
        ->bind('$rentalFee', env('float:CREDIT_SYSTEM_RENTAL_FEE'))
        ->bind('$priceCycle', env('int:CREDIT_SYSTEM_PRICE_CYCLE'))
        ->bind('$longRentalFee', env('float:CREDIT_SYSTEM_LONG_RENTAL_FEE'))
        ->bind('$limitIncreaseFee', env('float:CREDIT_SYSTEM_LIMIT_INCREASE_FEE'))
        ->bind('$violationFee', env('float:CREDIT_SYSTEM_VIOLATION_FEE'));

    $services->load('BikeShare\\Rent\\', '../src/Rent')
        ->bind(
            '$watchesConfig',
            [
                'stack' => env('int:WATCHES_STACK'),
                'longrental' => env('int:WATCHES_LONG_RENTAL'),
                'freetime' => env('int:WATCHES_FREE_TIME'),
                'flatpricecycle' => env('int:WATCHES_FLAT_PRICE_CYCLE'),
                'doublepricecycle' => env('int:WATCHES_DOUBLE_PRICE_CYCLE'),
                'doublepricecyclecap' => env('int:WATCHES_DOUBLE_PRICE_CYCLE_CAP'),
            ]
        )
        ->bind('$forceStack', env('bool:FORCE_STACK'));

    $services->load('BikeShare\\SmsConnector\\', '../src/SmsConnector')
        ->bind('$request', expr("service('request_stack').getCurrentRequest()"))
        ->bind('$configuration', env('json:SMS_CONNECTOR_CONFIG'));
    $services->get(SmsConnectorFactory::class)
        ->arg('$connectorName', env('SMS_CONNECTOR'));

    $services->alias(SmsSenderInterface::class, SmsSender::class);

    $services->alias(CodeGeneratorInterface::class, CodeGenerator::class);

    $services->alias(PhonePurifierInterface::class, PhonePurifier::class);

    $services->load('BikeShare\\EventListener\\', '../src/EventListener')
        ->exclude('../src/EventListener/LocaleListener.php')
        ->tag('kernel.event_listener');

    $services->get(\BikeShare\EventListener\LocaleListener::class)
        ->bind('$defaultLocale', '%kernel.default_locale%');

    $services->get(\BikeShare\EventListener\TooManyBikeRentEventListener::class)
        ->bind('$timeTooManyHours', env('int:WATCHES_TIME_TOO_MANY'))
        ->bind('$numberToMany', env('int:WATCHES_NUMBER_TOO_MANY'));
};
