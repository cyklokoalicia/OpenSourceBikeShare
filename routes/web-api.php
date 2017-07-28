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

Route::group(['prefix' => 'app.json', 'middleware' => 'auth'], function () {

    Route::group(['prefix' => 'stands', 'namespace' => 'Stands'], function () {
        Route::get('', 'StandsApiController@index')->name('app.stands.index');
        Route::get('{slug}', 'StandsApiController@show')->name('app.stands.show');
    });

    Route::group(['prefix' => 'bikes', 'namespace' => 'Bikes'], function () {
        Route::get('/', [
            'as' => 'app.bikes.index',
            'uses' => 'BikesController@index',
        ]);

        Route::get('/create', [
            'as' => 'app.bikes.create',
            'uses' => 'BikesController@create',
        ]);

        Route::get('{uuid}/edit', [
            'as' => 'app.bikes.edit',
            'uses' => 'BikesController@edit',
        ]);

        Route::post('/store', [
            'as' => 'app.bikes.store',
            'uses' => 'BikesController@store',
        ]);

        Route::post('/{uuid}/rent', [
            'as' => 'app.bikes.rent',
            'uses' => 'BikesController@rent',
        ]);

        Route::post('/{uuid}/return', [
            'as' => 'app.bikes.return',
            'uses' => 'BikesController@returnBike',
        ]);

        Route::put('{uuid}', [
            'as' => 'app.bikes.update',
            'uses' => 'BikesController@update',
        ]);

        Route::get('/{uuid}', [
            'as' => 'app.bikes.show',
            'uses' => 'BikesController@show',
        ]);
    });

    // TODO moved under users
    Route::group(['prefix' => 'rents', 'namespace' => 'Rents'], function () {

        Route::get('/', [
            'as' => 'app.rents.index',
            'uses' => 'RentsController@index',
        ]);

        Route::get('{uuid}', [
            'as' => 'app.rents.show',
            'uses' => 'RentsController@show',
        ]);

    });

    Route::group(['middleware' => 'role:admin'], function () {
        Route::get('/dashboard', [
            'as' => 'app.dashboard',
            'uses' => 'DashboardController@index',
        ]);

        Route::group(['prefix' => 'rents', 'namespace' => 'Rents'], function () {

            Route::get('/', [
                'as' => 'app.rents.index',
                'uses' => 'RentsController@index',
            ]);

            Route::get('{uuid}', [
                'as' => 'app.rents.show',
                'uses' => 'RentsController@show',
            ]);

        });

        Route::group(['prefix' => 'users'], function () {

            Route::group(['prefix' => '{uuid}'], function () {
                Route::get('profile', [
                    'as' => 'app.users.profile.show',
                    'uses' => 'ProfileController@show',
                ]);

                Route::get('edit', 'UsersController@edit')->name('app.users.edit');
            });

            Route::get('/', 'UsersController@index')->name('app.users.index');
            Route::get('/create', 'UsersController@create')->name('app.users.create');
            Route::get('{uuid}/edit', 'UsersController@edit')->name('app.users.edit');
            Route::post('', 'UsersController@store')->name('app.users.store');
            Route::put('{uuid}', 'UsersController@update')->name('app.users.update');
        });

    });

});
