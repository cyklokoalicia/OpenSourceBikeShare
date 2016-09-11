<?php

namespace BikeShare\Providers;

use BikeShare\Http\Services\AppConfig;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('AppConfig', function ($app) {
            return new AppConfig($app->config['bike-share']);
        });
    }
}
