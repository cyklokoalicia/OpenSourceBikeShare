<?php

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add('home', '/')
        ->controller([\BikeShare\Controller\HomeController::class, 'index']);
    $routes->add('command', '/command.php')
        ->controller([\BikeShare\Controller\CommandController::class, 'index']);
    $routes->add('scan_bike', '/scan.php/rent/{bikeNumber}')
        ->requirements(['id' => '\d+'])
        ->controller([\BikeShare\Controller\ScanController::class, 'index']);
    $routes->add('scan_stand', '/scan.php/return/{standName}')
        ->requirements(['standName' => '\w+'])
        ->controller([\BikeShare\Controller\ScanController::class, 'index']);
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
    $routes->add('user_confirm', '/user/confirm/{key}')
        ->defaults(['key' => ''])
        ->controller([\BikeShare\Controller\EmailConfirmController::class, 'index']);
    $routes->add('login', '/login')
        ->controller([\BikeShare\Controller\SecurityController::class, 'login']);
    $routes->add('logout', '/logout')
        ->controller([\BikeShare\Controller\SecurityController::class, 'logout']);
    $routes->add('reset_password', '/resetPassword')
        ->controller([\BikeShare\Controller\SecurityController::class, 'resetPassword']);

    $routes->add('api_stand_index', '/api/stand')
        ->methods(['GET'])
        ->controller([\BikeShare\Controller\Api\StandController::class, 'index']);
    $routes->add('api_bike_index', '/api/bike')
        ->methods(['GET'])
        ->controller([\BikeShare\Controller\Api\BikeController::class, 'index']);
    $routes->add('api_bike_item', '/api/bike/{bikeNumber}')
        ->requirements(['bikeNumber' => '\d+'])
        ->methods(['GET'])
        ->controller([\BikeShare\Controller\Api\BikeController::class, 'item']);
    $routes->add('api_bike_last_usage', '/api/bikeLastUsage/{bikeNumber}')
        ->methods(['GET'])
        ->requirements(['bikeNumber' => '\d+'])
        ->controller([\BikeShare\Controller\Api\BikeController::class, 'lastUsage']);
    $routes->add('api_coupon_index', '/api/coupon')
        ->methods(['GET'])
        ->controller([\BikeShare\Controller\Api\CouponController::class, 'index']);
    $routes->add('api_coupon_sell', '/api/coupon/sell')
        ->methods(['POST'])
        ->controller([\BikeShare\Controller\Api\CouponController::class, 'sell']);
    $routes->add('api_coupon_generate', '/api/coupon/generate')
        ->methods(['POST'])
        ->controller([\BikeShare\Controller\Api\CouponController::class, 'generate']);
    $routes->add('api_user_index', '/api/user')
        ->methods(['GET'])
        ->controller([\BikeShare\Controller\Api\UserController::class, 'index']);
    $routes->add('api_user_item', '/api/user/{userId}')
        ->methods(['GET'])
        ->requirements(['userId' => '\d+'])
        ->controller([\BikeShare\Controller\Api\UserController::class, 'item']);
    $routes->add('api_user_item_update', '/api/user/{userId}')
        ->methods(['PUT'])
        ->requirements(['userId' => '\d+'])
        ->controller([\BikeShare\Controller\Api\UserController::class, 'update']);
    $routes->add('api_credit_add', '/api/credit')
        ->methods(['PUT'])
        ->controller([\BikeShare\Controller\Api\CreditController::class, 'add']);
    $routes->add('api_report_daily', '/api/report/daily')
        ->methods(['GET'])
        ->controller([\BikeShare\Controller\Api\ReportController::class, 'daily']);
    $routes->add('api_report_users', '/api/report/user/{year}')
        ->methods(['GET'])
        ->defaults(['year' => date('Y')])
        ->requirements(['year' => '\d+'])
        ->controller([\BikeShare\Controller\Api\ReportController::class, 'user']);

    $routes->add('personal_stats_year', '/personalStats/year/{year}')
        ->methods(['GET'])
        ->defaults(['year' => date('Y')])
        ->requirements(['year' => '\d+'])
        ->controller([\BikeShare\Controller\PersonalStatsController::class, 'yearStats']);
};
