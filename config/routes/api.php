<?php

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add('api_v1_auth_token', '/api/v1/auth/token')
        ->methods(['POST'])
        ->controller([\BikeShare\Controller\Api\V1\AuthController::class, 'token']);
    $routes->add('api_v1_auth_refresh', '/api/v1/auth/refresh')
        ->methods(['POST'])
        ->controller([\BikeShare\Controller\Api\V1\AuthController::class, 'refresh']);
    $routes->add('api_v1_auth_logout', '/api/v1/auth/logout')
        ->methods(['POST'])
        ->controller([\BikeShare\Controller\Api\V1\AuthController::class, 'logout']);
    $routes->add('api_v1_auth_register', '/api/v1/auth/register')
        ->methods(['POST'])
        ->controller([\BikeShare\Controller\Api\V1\AuthController::class, 'register']);
    $routes->add('api_v1_auth_cities', '/api/v1/auth/cities')
        ->methods(['GET'])
        ->controller([\BikeShare\Controller\Api\V1\AuthController::class, 'cities']);
    $routes->add('api_v1_stands', '/api/v1/admin/stands')
        ->methods(['GET'])
        ->controller([\BikeShare\Controller\Api\V1\Admin\StandsController::class, 'index']);
    $routes->add('api_v1_admin_stand_item', '/api/v1/admin/stands/{standName}')
        ->requirements(['standName' => '\w+'])
        ->methods(['GET'])
        ->controller([\BikeShare\Controller\Api\V1\Admin\StandsController::class, 'item']);
    $routes->add('api_v1_stand_bikes', '/api/v1/stands/{standName}/bikes')
        ->requirements(['standName' => '\w+'])
        ->methods(['GET'])
        ->controller([\BikeShare\Controller\Api\V1\StandsController::class, 'bike']);
    $routes->add('api_v1_stand_markers', '/api/v1/stands/markers')
        ->methods(['GET'])
        ->controller([\BikeShare\Controller\Api\V1\StandsController::class, 'markers']);
    $routes->add('api_v1_admin_stand_notes_delete', '/api/v1/admin/stands/{standName}/notes')
        ->requirements(['standName' => '\w+'])
        ->methods(['DELETE'])
        ->controller([\BikeShare\Controller\Api\V1\Admin\StandsController::class, 'removeNote']);

    $routes->add('api_v1_admin_bikes', '/api/v1/admin/bikes')
        ->methods(['GET'])
        ->controller([\BikeShare\Controller\Api\V1\Admin\BikesController::class, 'index']);
    $routes->add('api_v1_bike_item', '/api/v1/admin/bikes/{bikeNumber}')
        ->requirements(['bikeNumber' => '\d+'])
        ->methods(['GET'])
        ->controller([\BikeShare\Controller\Api\V1\Admin\BikesController::class, 'item']);
    $routes->add('api_v1_bike_last_usage', '/api/v1/admin/bikes/{bikeNumber}/last-usage')
        ->methods(['GET'])
        ->requirements(['bikeNumber' => '\d+'])
        ->controller([\BikeShare\Controller\Api\V1\Admin\BikesController::class, 'lastUsage']);
    $routes->add('api_v1_rentals', '/api/v1/rentals')
        ->methods(['POST'])
        ->controller([\BikeShare\Controller\Api\V1\RentalsController::class, 'create']);
    $routes->add('api_v1_returns', '/api/v1/returns')
        ->methods(['POST'])
        ->controller([\BikeShare\Controller\Api\V1\ReturnsController::class, 'create']);
    $routes->add('api_v1_admin_rentals_force', '/api/v1/admin/rentals/force')
        ->methods(['POST'])
        ->controller([\BikeShare\Controller\Api\V1\Admin\RentalsController::class, 'forceCreate']);
    $routes->add('api_v1_admin_returns_force', '/api/v1/admin/returns/force')
        ->methods(['POST'])
        ->controller([\BikeShare\Controller\Api\V1\Admin\ReturnsController::class, 'forceCreate']);
    $routes->add('api_v1_admin_bike_set_code', '/api/v1/admin/bikes/{bikeNumber}/lock-code')
        ->requirements(['bikeNumber' => '\d+'])
        ->methods(['PATCH'])
        ->controller([\BikeShare\Controller\Api\V1\Admin\BikesController::class, 'setCode']);
    $routes->add('api_v1_admin_reverts', '/api/v1/admin/reverts')
        ->methods(['POST'])
        ->controller([\BikeShare\Controller\Api\V1\Admin\RevertsController::class, 'create']);
    $routes->add('api_v1_admin_bike_notes_delete', '/api/v1/admin/bikes/{bikeNumber}/notes')
        ->requirements(['bikeNumber' => '\d+'])
        ->methods(['DELETE'])
        ->controller([\BikeShare\Controller\Api\V1\Admin\BikesController::class, 'removeNote']);
    $routes->add('api_v1_bike_trip', '/api/v1/admin/bikes/{bikeNumber}/trip')
        ->requirements(['bikeNumber' => '\d+'])
        ->methods(['GET'])
        ->controller([\BikeShare\Controller\Api\V1\Admin\BikesController::class, 'bikeTrip']);

    $routes->add('api_v1_admin_coupons', '/api/v1/admin/coupons')
        ->methods(['GET'])
        ->controller([\BikeShare\Controller\Api\V1\Admin\CouponsController::class, 'index']);
    $routes->add('api_v1_admin_coupon_sell', '/api/v1/admin/coupons/{coupon}/sell')
        ->requirements(['coupon' => '\w+'])
        ->methods(['POST'])
        ->controller([\BikeShare\Controller\Api\V1\Admin\CouponsController::class, 'sellCoupon']);
    $routes->add('api_v1_coupon_redeem', '/api/v1/coupons/redeem')
        ->methods(['POST'])
        ->controller([\BikeShare\Controller\Api\V1\CouponsController::class, 'useCoupon']);
    $routes->add('api_v1_admin_coupon_generate', '/api/v1/admin/coupons/generate')
        ->methods(['POST'])
        ->controller([\BikeShare\Controller\Api\V1\Admin\CouponsController::class, 'generate']);

    $routes->add('api_v1_admin_users', '/api/v1/admin/users')
        ->methods(['GET'])
        ->controller([\BikeShare\Controller\Api\V1\Admin\UsersController::class, 'index']);
    $routes->add('api_v1_admin_user_item', '/api/v1/admin/users/{userId}')
        ->methods(['GET'])
        ->requirements(['userId' => '\d+'])
        ->controller([\BikeShare\Controller\Api\V1\Admin\UsersController::class, 'item']);
    $routes->add('api_v1_admin_user_item_update', '/api/v1/admin/users/{userId}')
        ->methods(['PATCH'])
        ->requirements(['userId' => '\d+'])
        ->controller([\BikeShare\Controller\Api\V1\Admin\UsersController::class, 'update']);
    $routes->add('api_v1_me_city', '/api/v1/me/city')
        ->methods(['PATCH'])
        ->controller([\BikeShare\Controller\Api\V1\UsersController::class, 'changeCity']);
    $routes->add('api_v1_me_bikes', '/api/v1/me/bikes')
        ->methods(['GET'])
        ->controller([\BikeShare\Controller\Api\V1\UsersController::class, 'userBike']);
    $routes->add('api_v1_me_limits', '/api/v1/me/limits')
        ->methods(['GET'])
        ->controller([\BikeShare\Controller\Api\V1\UsersController::class, 'userLimit']);
    $routes->add('api_v1_me_credit_history', '/api/v1/me/credit-history')
        ->methods(['GET'])
        ->controller([\BikeShare\Controller\Api\V1\UsersController::class, 'creditHistory']);
    $routes->add('api_v1_me_trips', '/api/v1/me/trips')
        ->methods(['GET'])
        ->controller([\BikeShare\Controller\Api\V1\UsersController::class, 'trips']);
    $routes->add('api_v1_user_phone_confirm_request', '/api/v1/user/phone-confirm/request')
        ->methods(['POST'])
        ->controller([\BikeShare\Controller\Api\V1\UsersController::class, 'phoneConfirmRequest']);
    $routes->add('api_v1_user_phone_confirm_verify', '/api/v1/user/phone-confirm/verify')
        ->methods(['POST'])
        ->controller([\BikeShare\Controller\Api\V1\UsersController::class, 'phoneConfirmVerify']);

    $routes->add('api_v1_admin_user_credit_add', '/api/v1/admin/users/{userId}/credit')
        ->methods(['PUT'])
        ->requirements(['userId' => '\d+'])
        ->controller([\BikeShare\Controller\Api\V1\Admin\UsersController::class, 'addCredit']);
    $routes->add('api_v1_admin_report_daily', '/api/v1/admin/reports/daily')
        ->methods(['GET'])
        ->controller([\BikeShare\Controller\Api\V1\Admin\ReportsController::class, 'daily']);
    $routes->add('api_v1_admin_report_users', '/api/v1/admin/reports/users/{year}')
        ->methods(['GET'])
        ->defaults(['year' => date('Y')])
        ->requirements(['year' => '\d+'])
        ->controller([\BikeShare\Controller\Api\V1\Admin\ReportsController::class, 'user']);
    $routes->add('api_v1_admin_report_inactive_bikes', '/api/v1/admin/reports/inactive-bikes')
        ->methods(['GET'])
        ->controller([\BikeShare\Controller\Api\V1\Admin\ReportsController::class, 'inactiveBikes']);
};
