<?php

Route::get('/', function () {
    return view('welcome');
});

Route::get('/docs', function () {
    return view('docs.index');
});

Route::get('logs', '\Rap2hpoutre\LaravelLogViewer\LogViewerController@index');
