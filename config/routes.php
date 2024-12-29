<?php

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add('home', '/')
        ->controller([\BikeShare\Controller\HomeController::class, 'index']);
    $routes->add('command', '/command.php')
        ->controller([\BikeShare\Controller\CommandController::class, 'index']);
    $routes->add('scan', '/scan.php/{action}/{id}')
        ->controller([\BikeShare\Controller\ScanController::class, 'index']);
    $routes->add('admin_old', '/admin.php')
        ->controller([\BikeShare\Controller\AdminController::class, 'index']);
    $routes->add('admin', '/admin')
        ->controller([\BikeShare\Controller\AdminController::class, 'index']);
    $routes->add('register', '/register.php')
        ->controller([\BikeShare\Controller\RegisterController::class, 'index']);
    $routes->add('sms_request_old', '/sms/receive.php')
        ->controller([\BikeShare\Controller\SmsRequestController::class, 'index']);
    $routes->add('sms_request', '/receive.php')
        ->controller([\BikeShare\Controller\SmsRequestController::class, 'index']);
    $routes->add('agree', '/agree.php')
        ->controller([\BikeShare\Controller\AgreeController::class, 'index']);
    $routes->add('login', '/login')
        ->controller([\BikeShare\Controller\SecurityController::class, 'login']);
    $routes->add('logout', '/logout')
        ->controller([\BikeShare\Controller\SecurityController::class, 'logout']);
    $routes->add('reset_password', '/resetPassword')
        ->controller([\BikeShare\Controller\SecurityController::class, 'resetPassword']);

    $routes->add('api_bike_index', '/api/bike')
        ->methods(['GET'])
        ->controller([\BikeShare\Controller\Api\BikeController::class, 'index']);
    $routes->add('api_bike_item', '/api/bike/{bikeNumber}')
        ->methods(['GET'])
        ->controller([\BikeShare\Controller\Api\BikeController::class, 'item']);
    $routes->add('api_bike_last_usage', '/api/bikeLastUsage/{bikeNumber}')
        ->methods(['GET'])
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

    $routes->add('personal_stats_year', '/personalStats/year/{year}')
        ->methods(['GET'])
        ->controller([\BikeShare\Controller\PersonalStatsController::class, 'yearStats']);
};