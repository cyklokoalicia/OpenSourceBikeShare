<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

$api = app('Dingo\Api\Routing\Router');

$api->version('v1', ['namespace' => 'BikeShare\Http\Controllers\Api\v1'], function ($api) {

    $api->group(['prefix' => 'auth', 'namespace' => 'Auth'], function ($api) {
        $api->post('verify-phone-number', [
            'as' => 'api.auth.verify-phone-number',
            'uses' => 'RegisterController@verifyPhoneNumber',
        ]);

        $api->post('register', [
            'as' => 'api.auth.register',
            'uses' => 'RegisterController@register',
        ]);

        $api->get('agree/{token}', [
            'as' => 'api.auth.agree',
            'uses' => 'RegisterController@agree',
        ]);

        $api->post('authenticate', [
            'as' => 'api.auth.login',
            'uses' => 'LoginController@authenticate',
        ]);

        // Password Reset Routes...
        $api->post('password/email', [
            'as' => 'api.auth.password.email',
            'uses' => 'ForgotPasswordController@sendResetLinkEmail'
        ]);

        $api->post('password/reset', [
            'as' => 'api.auth.password.post.reset',
            'uses' => 'ResetPasswordController@reset'
        ]);


    });

    $api->group(['middleware' => 'jwt.auth'], function ($api) {

        $api->group(['prefix' => 'me', 'namespace' => 'Me'], function ($api) {
            $api->get('', [
                'as' => 'api.me',
                'uses' => 'MeController@getInfo',
            ]);

            $api->get('rents', [
                'as' => 'api.me.rents',
                'uses' => 'MeController@getAllRents',
            ]);

            $api->get('rents/active', [
                'as' => 'api.me.rents.active',
                'uses' => 'MeController@getActiveRents',
            ]);

        });

        $api->group(['prefix' => 'stands', 'namespace' => 'Stands'], function ($api) {
            $api->get('', [
                'as' => 'api.stands',
                'uses' => 'StandsController@index',
            ]);
        });

        $api->group(['prefix' => 'rents', 'namespace' => 'Rents'], function ($api) {
            $api->post('{uuid}/close', [
                'as' => 'api.rents.close',
                'uses' => 'RentsController@close',
            ]);

            $api->post('', [
                'as' => 'api.rents.store',
                'uses' => 'RentsController@store',
            ]);
        });

        $api->group(['middleware' => 'role:admin'], function ($api) {
            $api->group(['prefix' => 'rents', 'namespace' => 'Rents'], function ($api) {
                $api->get('', [
                    'as' => 'api.rents.index',
                    'uses' => 'RentsController@index',
                ]);

                $api->get('/active', [
                    'as' => 'api.rents.active',
                    'uses' => 'RentsController@active',
                ]);

                $api->get('/history', [
                    'as' => 'api.rents.history',
                    'uses' => 'RentsController@history',
                ]);
            });

            $api->group(['prefix' => 'users', 'namespace' => 'Users'], function ($api) {

                $api->group(['prefix' => '{uuid}'], function ($api) {
                    $api->get('/restore', [
                        'as' => 'api.users.restore',
                        'uses' => 'UsersController@restore',
                    ]);
                });

                $api->get('', [
                    'as' => 'api.users.index',
                    'uses' => 'UsersController@index',
                ]);

                $api->post('', [
                    'as' => 'api.users.store',
                    'uses' => 'UsersController@store',
                ]);

                $api->get('{uuid}', [
                    'as' => 'api.users.show',
                    'uses' => 'UsersController@show',
                ]);

                $api->put('{uuid}', [
                    'as' => 'api.users.update',
                    'uses' => 'UsersController@update',
                ]);

                $api->delete('{uuid}', [
                    'as' => 'api.users.destroy',
                    'uses' => 'UsersController@destroy',
                ]);
            });

            $api->group(['prefix' => 'bikes', 'namespace' => 'Bikes'], function ($api) {

                $api->group(['prefix' => '{uuid}'], function ($api) {
                    $api->post('/rent', [
                        'as' => 'api.bikes.rent',
                        'uses' => 'BikesController@rentBike',
                    ]);
                });

                $api->get('', [
                    'as' => 'api.bikes.index',
                    'uses' => 'BikesController@index',
                ]);

                $api->post('', [
                    'as' => 'api.bikes.store',
                    'uses' => 'BikesController@store',
                ]);

                $api->get('{uuid}', [
                    'as' => 'api.bikes.show',
                    'uses' => 'BikesController@show',
                ]);

                $api->delete('{uuid}', [
                    'as' => 'api.bikes.destroy',
                    'uses' => 'BikesController@destroy',
                ]);
            });

            $api->group(['prefix' => 'notes', 'namespace' => 'Notes'], function ($api) {
                $api->delete('{uuid}', [
                    'as' => 'api.notes.destroy',
                    'uses' => 'NotesController@destroy',
                ]);
            });

            $api->group(['prefix' => 'coupons', 'namespace' => 'Coupons'], function ($api) {
                $api->get('', [
                    'as' => 'api.coupons.index',
                    'uses' => 'CouponsController@index',
                ]);

                $api->post('', [
                    'as' => 'api.coupons.store',
                    'uses' => 'CouponsController@store',
                ]);

                $api->get('{uuid}/sell', [
                    'as' => 'api.coupons.sell',
                    'uses' => 'CouponsController@sell',
                ]);

                $api->get('{uuid}/validate', [
                    'as' => 'api.coupons.validate',
                    'uses' => 'CouponsController@validateCoupon',
                ]);

                $api->get('{uuid}', [
                    'as' => 'api.coupons.show',
                    'uses' => 'CouponsController@show',
                ]);
            });

        });
    });
});






Route::group(['middleware' => 'auth:api'], function () {

});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');
