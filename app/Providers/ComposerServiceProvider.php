<?php

namespace BikeShare\Providers;

use BikeShare\Http\ViewComposers\AppComposer;
use BikeShare\Http\ViewComposers\UserComposer;
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
        view()->composer('user.layouts.app', UserComposer::class);
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
