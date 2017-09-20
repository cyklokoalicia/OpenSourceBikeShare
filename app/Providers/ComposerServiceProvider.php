<?php

namespace BikeShare\Providers;

use BikeShare\Http\ViewComposers\AppComposer;
use Illuminate\Support\ServiceProvider;

class ComposerServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        // Uing class based composers...
        view()->composer('admin.layouts.app', AppComposer::class);
        //view()->composer('*', AppComposer::class);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
