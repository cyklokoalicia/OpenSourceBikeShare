<?php

// Authentication Routes...
Route::group(['prefix' => 'auth', 'namespace' => 'Auth'], function () {
    Route::get('login', 'LoginController@showLoginForm')->name('app.auth.login');
    Route::post('login', 'LoginController@login')->name('app.auth.post.login');
    Route::get('logout', 'LoginController@logout')->name('app.auth.logout');

    // Registration Routes...
    Route::get('register', 'RegisterController@showRegistrationForm')->name('app.auth.register');
    Route::post('register', 'RegisterController@register')->name('app.auth.post.register');

    // Password Reset Routes...
    Route::get('password/reset', 'ForgotPasswordController@showLinkRequestForm')->name('app.auth.password.reset');

    Route::post('password/email', 'ForgotPasswordController@sendResetLinkEmail')->name('app.auth.password.email');

    Route::get('password/reset/{token}', 'ResetPasswordController@showResetForm')
        ->name('app.auth.password.reset.token');

    Route::post('password/reset', 'ResetPasswordController@reset')->name('app.auth.password.post.reset');
});

Route::group(['middleware' => 'admin'], function () {
    Route::get('home', function () {
        return 'ok';
    })->name('app.home');
});
