<?php

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add('switch_language', '/switchLanguage/{locale}')
        ->requirements(['locale' => '[a-z]{2}'])
        ->defaults(['locale' => 'en'])
        ->controller([\BikeShare\Controller\LanguageController::class, 'switchLanguage']);
    $routes->add('js_translations', '/js/translations.json')
        ->controller([\BikeShare\Controller\LanguageController::class, 'getTranslations']);
    $routes->add('home', '/')
        ->controller([\BikeShare\Controller\HomeController::class, 'index']);
    $routes->add('scan_bike', '/scan.php/rent/{bikeNumber}')
        ->requirements(['bikeNumber' => '\d+'])
        ->controller([\BikeShare\Controller\ScanController::class, 'rentBike']);
    $routes->add('scan_stand', '/scan.php/return/{standName}')
        ->requirements(['standName' => '\w+'])
        ->controller([\BikeShare\Controller\ScanController::class, 'returnBike']);
    $routes->add('admin', '/admin')
        ->controller([\BikeShare\Controller\AdminController::class, 'index']);
    $routes->add('register_old', '/register.php')
        ->controller([\BikeShare\Controller\RegisterController::class, 'index']);
    $routes->add('register', '/register')
        ->controller([\BikeShare\Controller\RegisterController::class, 'index']);
    $routes->add('sms_request_old', '/sms/receive.php')
        ->controller([\BikeShare\Controller\SmsRequestController::class, 'index']);
    $routes->add('sms_request', '/receive.php')
        ->controller([\BikeShare\Controller\SmsRequestController::class, 'index']);
    $routes->add('user_confirm_email', '/user/confirm/email/{key}')
        ->defaults(['key' => ''])
        ->controller([\BikeShare\Controller\EmailConfirmController::class, 'index']);
    $routes->add('user_confirm_phone', '/user/confirm/phone/{key}')
        ->defaults(['key' => ''])
        ->controller([\BikeShare\Controller\PhoneConfirmController::class, 'index']);
    $routes->add('login', '/login')
        ->controller([\BikeShare\Controller\SecurityController::class, 'login']);
    $routes->add('logout', '/logout')
        ->controller([\BikeShare\Controller\SecurityController::class, 'logout']);
    $routes->add('reset_password', '/resetPassword')
        ->controller([\BikeShare\Controller\SecurityController::class, 'resetPassword']);
    $routes->add('user_settings_geolocation', '/user/settings/geolocation')
        ->methods(['PUT'])
        ->controller([\BikeShare\Controller\UserSettingsController::class, 'saveGeolocation']);
    $routes->add('user_profile', '/user/profile')
        ->controller([\BikeShare\Controller\UserController::class, 'profile']);

    $routes->import(__DIR__ . '/routes/api.php');

    $routes->add('personal_stats_year', '/personalStats/year/{year}')
        ->methods(['GET'])
        ->defaults(['year' => date('Y')])
        ->requirements(['year' => '\d+'])
        ->controller([\BikeShare\Controller\PersonalStatsController::class, 'yearStats']);

    $routes->add('credit_history', '/credit/history')
        ->methods(['GET'])
        ->controller([\BikeShare\Controller\CreditHistoryController::class, 'history']);

    $routes->add('qr_code_generator', '/admin/qrCodeGenerator')
        ->controller([\BikeShare\Controller\QrCodeGeneratorController::class, 'index']);
};
