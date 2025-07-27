<?php

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add('home', '/')
        ->controller([\BikeShare\Controller\HomeController::class, 'index']);
    $routes->add('command', '/command.php')
        ->controller([\BikeShare\Controller\CommandController::class, 'index']);
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

    $routes->add('api_stand_index', '/api/stand')
        ->methods(['GET'])
        ->controller([\BikeShare\Controller\Api\StandController::class, 'index']);
    $routes->add('api_stand_item', '/api/stand/{standName}/bike')
        ->requirements(['standName' => '\w+'])
        ->methods(['GET'])
        ->controller([\BikeShare\Controller\Api\StandController::class, 'bike']);
    $routes->add('api_stand_remove_note', '/api/stand/{standName}/removeNote')
        ->requirements(['standName' => '\w+'])
        ->methods(['DELETE'])
        ->controller([\BikeShare\Controller\Api\StandController::class, 'removeNote']);
    $routes->add('api_bike_index', '/api/bike')
        ->methods(['GET'])
        ->controller([\BikeShare\Controller\Api\BikeController::class, 'index']);
    $routes->add('api_bike_item', '/api/bike/{bikeNumber}')
        ->requirements(['bikeNumber' => '\d+'])
        ->methods(['GET'])
        ->controller([\BikeShare\Controller\Api\BikeController::class, 'item']);
    $routes->add('api_bike_last_usage', '/api/bike/{bikeNumber}/lastUsage')
        ->methods(['GET'])
        ->requirements(['bikeNumber' => '\d+'])
        ->controller([\BikeShare\Controller\Api\BikeController::class, 'lastUsage']);
    $routes->add('api_bike_rent', '/api/bike/{bikeNumber}/rent')
        ->methods(['PUT'])
        ->requirements(['bikeNumber' => '\d+'])
        ->controller([\BikeShare\Controller\Api\BikeController::class, 'rentBike']);
    $routes->add('api_bike_return', '/api/bike/{bikeNumber}/return/{standName}')
        ->requirements(['bikeNumber' => '\d+'])
        ->requirements(['standName' => '\w+'])
        ->methods(['PUT'])
        ->controller([\BikeShare\Controller\Api\BikeController::class, 'returnBike']);
    $routes->add('api_bike_revert', '/api/bike/{bikeNumber}/revert')
        ->requirements(['bikeNumber' => '\d+'])
        ->requirements(['standName' => '\w+'])
        ->methods(['PUT'])
        ->controller([\BikeShare\Controller\Api\BikeController::class, 'revertBike']);
    $routes->add('api_bike_remove_note', '/api/bike/{bikeNumber}/removeNote')
        ->requirements(['bikeNumber' => '\d+'])
        ->methods(['DELETE'])
        ->controller([\BikeShare\Controller\Api\BikeController::class, 'removeNote']);
    $routes->add('api_coupon_index', '/api/coupon')
        ->methods(['GET'])
        ->controller([\BikeShare\Controller\Api\CouponController::class, 'index']);
    $routes->add('api_coupon_sell', '/api/coupon/sell')
        ->methods(['POST'])
        ->controller([\BikeShare\Controller\Api\CouponController::class, 'sellCoupon']);
    $routes->add('api_coupon_use', '/api/coupon/use')
        ->methods(['POST'])
        ->controller([\BikeShare\Controller\Api\CouponController::class, 'useCoupon']);
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
    $routes->add('api_user_change_city', '/api/user/changeCity')
        ->methods(['PUT'])
        ->controller([\BikeShare\Controller\Api\UserController::class, 'changeCity']);
    $routes->add('api_user_bike', '/api/user/bike')
        ->methods(['GET'])
        ->controller([\BikeShare\Controller\Api\UserController::class, 'userBike']);
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

    $routes->add('qr_code_generator', '/admin/qrCodeGenerator')
        ->controller([\BikeShare\Controller\QrCodeGeneratorController::class, 'index']);
};
