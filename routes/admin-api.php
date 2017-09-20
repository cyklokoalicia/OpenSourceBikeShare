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

Route::group(['prefix' => 'json', 'middleware' => 'admin'], function () {

    Route::group(['prefix' => 'stands', 'namespace' => 'Stands'], function () {
        Route::get('', 'StandsApiController@index')->name('admin.json.stands.index');
        Route::get('{slug}', 'StandsApiController@show')->name('admin.json.stands.show');
    });
});
