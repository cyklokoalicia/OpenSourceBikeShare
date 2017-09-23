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

Api::version('v1', ['namespace' => 'BikeShare\Http\Controllers\Api\v1'], function () {

    Api::group(['prefix' => 'auth', 'namespace' => 'Auth'], function () {
        Api::post('verify-phone-number', 'RegisterController@verifyPhoneNumber')->name('api.auth.verify-phone-number');
        Api::post('register', 'RegisterController@register')->name('api.auth.register');
        Api::get('agree/{token}', 'RegisterController@agree')->name('api.auth.agree');
        Api::post('authenticate', 'LoginController@authenticate')->name('api.auth.login');

        // Password Reset Routes...
        Api::post('password/email', 'ForgotPasswordController@sendResetLinkEmail')->name('api.auth.password.email');
        Api::post('password/reset', 'ResetPasswordController@reset')->name('api.auth.password.post.reset');
    });

    Api::group(['middleware' => 'jwt.auth'], function () {

        Api::group(['prefix' => 'me', 'namespace' => 'Me'], function () {
            Api::get('', 'MeController@getInfo')->name('api.me');
            Api::get('closest-stands', 'MeController@closestStands')->name('api.me.closest-stands');
            Api::get('rents', 'MeController@getAllRents')->name('api.me.rents');
            Api::get('rents/active', 'MeController@getActiveRents')->name('api.me.rents.active');
        });

        Api::group(['prefix' => 'stands', 'namespace' => 'Stands'], function () {
            Api::get('', 'StandsController@index')->name('api.stands.index');
            Api::get('{uuid}', 'StandsController@show')->name('api.stands.show');
        });

        Api::group(['prefix' => 'rents'], function () {
            Api::group(['prefix' => 'qrscan', 'namespace' => 'QrCodes'], function () {
                Api::post('bikes/{bikeNum}', 'QrCodesController@rentBike');
                Api::post('bikes/{bikeNum}/stands/{standName}', 'QrCodesController@returnBike');
            });

            Api::group(['namespace' => 'Rents'], function () {
                Api::post('', 'RentsController@store')->name('api.rents.store');
                Api::get('{uuid}', 'RentsController@show')->name('api.rents.show');
                Api::post('{uuid}/close', 'RentsController@close')->name('api.rents.close');
            });
        });

        Api::group(['middleware' => 'role:admin'], function () {
            Api::group(['prefix' => 'rents', 'namespace' => 'Rents'], function () {
                Api::get('', 'RentsController@index')->name('api.rents.index');
                Api::get('/active', 'RentsController@active')->name('api.rents.active');
                Api::get('/history', 'RentsController@history')->name('api.rents.history');
            });

            Api::group(['prefix' => 'stands', 'namespace' => 'Stands'], function () {
                Api::get('', 'StandsController@index')->name('api.stands.index');
                Api::get('{uuid}', 'StandsController@show')->name('api.stands.show');
                Api::post('import', 'StandsController@import');
            });

            Api::group(['prefix' => 'users', 'namespace' => 'Users'], function () {

                Api::group(['prefix' => '{uuid}'], function () {
                    Api::get('/restore', 'UsersController@restore')->name('api.users.restore');
                });
                Api::get('', 'UsersController@index')->name('api.users.index');
                Api::post('', 'UsersController@store')->name('api.users.store');
                Api::get('{uuid}', 'UsersController@show')->name('api.users.show');
                Api::put('{uuid}', 'UsersController@update')->name('api.users.update');
                Api::delete('{uuid}', 'UsersController@destroy')->name('api.users.destroy');
            });

            Api::group(['prefix' => 'bikes', 'namespace' => 'Bikes'], function () {

                Api::group(['prefix' => '{uuid}'], function () {
                    Api::post('/rent', 'BikesController@rentBike')->name('api.bikes.rent');
                });

                Api::get('', 'BikesController@index')->name('api.bikes.index');
                Api::post('', 'BikesController@store')->name('api.bikes.store');
                Api::get('{uuid}', 'BikesController@show')->name('api.bikes.show');
                Api::delete('{uuid}', 'BikesController@destroy')->name('api.bikes.destroy');
            });

            Api::group(['prefix' => 'notes', 'namespace' => 'Notes'], function () {
                Api::delete('{uuid}', 'NotesController@destroy')->name('api.notes.destroy');
            });

            Api::group(['prefix' => 'coupons', 'namespace' => 'Coupons'], function () {
                Api::get('', 'CouponsController@index')->name('api.coupons.index');
                Api::post('', 'CouponsController@store')->name('api.coupons.store');
                Api::get('{uuid}/sell', 'CouponsController@sell')->name('api.coupons.sell');
                Api::get('{uuid}/validate', 'CouponsController@validateCoupon')->name('api.coupons.validate');
                Api::get('{uuid}', 'CouponsController@show')->name('api.coupons.show');
            });
        });
    });

    Api::group(['prefix' => 'sms', 'namespace' => 'Sms'], function () {
        Api::match(['get', 'post'],'/receive', 'SmsController@receive')->name('api.sms.receive');
    });
});

Route::group(['middleware' => 'auth:api'], function () {

});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');
