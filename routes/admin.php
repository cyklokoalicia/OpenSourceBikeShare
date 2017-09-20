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

// Authentication Routes...
Route::group(['prefix' => 'auth', 'namespace' => 'Auth'], function () {
    Route::get('login', [
        'as' => 'admin.auth.login',
        'uses' => 'LoginController@showLoginForm',
    ]);
    Route::post('login', [
        'as' => 'admin.auth.post.login',
        'uses' => 'LoginController@login',
    ]);
    Route::get('logout', [
        'as' => 'admin.auth.logout',
        'uses' => 'LoginController@logout',
    ]);

    // Registration Routes...
    //Route::get('register', [
    //    'as' => 'admin.auth.register',
    //    'uses' => 'RegisterController@showRegistrationForm'
    //]);
    //
    //Route::post('register', [
    //    'as' => 'admin.auth.post.register',
    //    'uses' => 'RegisterController@register'
    //]);

    // Password Reset Routes...
    Route::get('password/reset', [
        'as' => 'admin.auth.password.reset',
        'uses' => 'ForgotPasswordController@showLinkRequestForm',
    ]);

    Route::post('password/email', [
        'as' => 'admin.auth.password.email',
        'uses' => 'ForgotPasswordController@sendResetLinkEmail',
    ]);

    Route::get('password/reset/{token}', [
        'as' => 'admin.auth.password.reset.token',
        'uses' => 'ResetPasswordController@showResetForm',
    ]);

    Route::post('password/reset', [
        'as' => 'admin.auth.password.post.reset',
        'uses' => 'ResetPasswordController@reset',
    ]);
});

Route::group(['middleware' => 'admin'], function () {

    Route::get('logs', 'ActivitylogController@index')->name('admin.logs.index');

    Route::get('/home', [
        'as' => 'admin.home',
        'uses' => 'HomeController@index',
    ]);

    Route::group(['prefix' => 'stands', 'namespace' => 'Stands'], function () {
        Route::get('', 'StandsController@index')->name('admin.stands.index');
        Route::get('create', 'StandsController@create')->name('admin.stands.create');
        Route::post('', 'StandsController@store')->name('admin.stands.store');
        Route::get('{uuid}', 'StandsController@show')->name('admin.stands.show');
        Route::get('{uuid}/edit', 'StandsController@edit')->name('admin.stands.edit');
        Route::put('{uuid}', 'StandsController@update')->name('admin.stands.update');
        Route::delete('{uuid}/media/{id}', 'StandsController@destroyMedia')->name('admin.stand.media.destroy');
        //Route::post('media', 'StandsController@upload')->name('admin.stand.media.store');
    });

    Route::group(['prefix' => 'bikes', 'namespace' => 'Bikes'], function () {
        Route::get('/', [
            'as' => 'admin.bikes.index',
            'uses' => 'BikesController@index',
        ]);

        Route::get('/create', [
            'as' => 'admin.bikes.create',
            'uses' => 'BikesController@create',
        ]);

        Route::get('{uuid}/edit', [
            'as' => 'admin.bikes.edit',
            'uses' => 'BikesController@edit',
        ]);

        Route::post('/store', [
            'as' => 'admin.bikes.store',
            'uses' => 'BikesController@store',
        ]);

        Route::post('/{uuid}/rent', [
            'as' => 'admin.bikes.rent',
            'uses' => 'BikesController@rent',
        ]);

        Route::post('/{uuid}/return', [
            'as' => 'admin.bikes.return',
            'uses' => 'BikesController@returnBike',
        ]);

        Route::put('{uuid}', [
            'as' => 'admin.bikes.update',
            'uses' => 'BikesController@update',
        ]);

        Route::get('/{uuid}', [
            'as' => 'admin.bikes.show',
            'uses' => 'BikesController@show',
        ]);
    });

    // TODO moved under users
    Route::group(['prefix' => 'rents', 'namespace' => 'Rents'], function () {

        Route::get('/', [
            'as' => 'admin.rents.index',
            'uses' => 'RentsController@index',
        ]);

        Route::get('{uuid}', [
            'as' => 'admin.rents.show',
            'uses' => 'RentsController@show',
        ]);

    });

    Route::group(['middleware' => 'role:admin'], function () {
        Route::get('/dashboard', [
            'as' => 'admin.dashboard',
            'uses' => 'DashboardController@index',
        ]);

        Route::group(['prefix' => 'rents', 'namespace' => 'Rents'], function () {

            Route::get('/', [
                'as' => 'admin.rents.index',
                'uses' => 'RentsController@index',
            ]);

            Route::get('{uuid}', [
                'as' => 'admin.rents.show',
                'uses' => 'RentsController@show',
            ]);

        });

        Route::group(['prefix' => 'users'], function () {

            Route::group(['prefix' => '{uuid}'], function () {
                Route::get('profile', [
                    'as' => 'admin.users.profile.show',
                    'uses' => 'ProfileController@show',
                ]);

                Route::get('edit', 'UsersController@edit')->name('admin.users.edit');
            });

            Route::get('/', 'UsersController@index')->name('admin.users.index');
            Route::get('/create', 'UsersController@create')->name('admin.users.create');
            Route::get('{uuid}/edit', 'UsersController@edit')->name('admin.users.edit');
            Route::post('', 'UsersController@store')->name('admin.users.store');
            Route::put('{uuid}', 'UsersController@update')->name('admin.users.update');
        });

    });

});
