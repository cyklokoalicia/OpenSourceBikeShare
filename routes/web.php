<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of the routes that are handled
| by your application. Just tell Laravel the URIs it should respond
| to using a Closure or controller method. Build something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/docs', function () {
    return view('docs.index');
});

Route::get('logs', '\Rap2hpoutre\LaravelLogViewer\LogViewerController@index');

// Authentication Routes...
Route::group(['prefix' => 'auth', 'namespace' => 'Auth'], function () {
    Route::get('login', [
        'as' => 'auth.login',
        'uses' => 'LoginController@showLoginForm'
    ]);
    Route::post('login', [
        'as' => 'auth.post.login',
        'uses' => 'LoginController@login'
    ]);
    Route::get('logout', [
        'as' => 'auth.logout',
        'uses' => 'LoginController@logout'
    ]);

    // Registration Routes...
    //Route::get('register', [
    //    'as' => 'auth.register',
    //    'uses' => 'RegisterController@showRegistrationForm'
    //]);
    //
    //Route::post('register', [
    //    'as' => 'auth.post.register',
    //    'uses' => 'RegisterController@register'
    //]);

    // Password Reset Routes...
    Route::get('password/reset', [
        'as' => 'auth.password.reset',
        'uses' => 'ForgotPasswordController@showLinkRequestForm'
    ]);

    Route::post('password/email', [
        'as' => 'auth.password.email',
        'uses' => 'ForgotPasswordController@sendResetLinkEmail'
    ]);

    Route::get('password/reset/{token}', [
        'as' => 'auth.password.reset.token',
        'uses' => 'ResetPasswordController@showResetForm'
    ]);

    Route::post('password/reset', [
        'as' => 'auth.password.post.reset',
        'uses' => 'ResetPasswordController@reset'
    ]);
});

Route::group(['prefix' => 'app', 'middleware' => 'auth'], function () {
    Route::get('/home', [
        'as'   => 'app.home',
        'uses' => 'HomeController@index'
    ]);

    Route::group(['prefix' => 'stands', 'namespace' => 'Stands'], function () {
        Route::get('/', [
            'as'   => 'app.stands.index',
            'uses' => 'StandsController@index'
        ]);

        Route::get('/{uuid}', [
            'as'   => 'app.stands.show',
            'uses' => 'StandsController@show'
        ]);
    });

    Route::group(['prefix' => 'bikes', 'namespace' => 'Bikes'], function () {
        Route::get('/', [
            'as'   => 'app.bikes.index',
            'uses' => 'BikesController@index'
        ]);

        Route::post('/{uuid}/rent', [
            'as'   => 'app.bikes.rent',
            'uses' => 'BikesController@rent'
        ]);

        Route::post('/{uuid}/return', [
            'as'   => 'app.bikes.return',
            'uses' => 'BikesController@returnBike'
        ]);

        Route::get('/{uuid}', [
            'as'   => 'app.bikes.show',
            'uses' => 'BikesController@show'
        ]);
    });

    // TODO moved under users
    Route::group(['prefix' => 'rents', 'namespace' => 'Rents'], function () {

        Route::get('/', [
            'as'   => 'app.rents.index',
            'uses' => 'RentsController@index'
        ]);

        Route::get('{uuid}', [
            'as'   => 'app.rents.show',
            'uses' => 'RentsController@show'
        ]);

    });

    Route::group(['middleware' => 'role:admin'], function () {
        Route::get('/dashboard', [
            'as'   => 'app.dashboard',
            'uses' => 'DashboardController@index'
        ]);

        Route::group(['prefix' => 'users'], function () {

            Route::group(['prefix' => '{uuid}'], function () {
                Route::get('profile', [
                    'as'   => 'app.users.profile.show',
                    'uses' => 'ProfileController@show'
                ]);
            });

            Route::group(['prefix' => 'rents', 'namespace' => 'Rents'], function () {

                Route::get('/', [
                    'as'   => 'app.users.rents.index',
                    'uses' => 'RentsController@index'
                ]);

                Route::get('{uuid}', [
                    'as'   => 'app.users.rents.show',
                    'uses' => 'RentsController@show'
                ]);

            });

            Route::get('/', [
                'as'   => 'app.users.index',
                'uses' => 'UsersController@index'
            ]);



        });

    });

});
