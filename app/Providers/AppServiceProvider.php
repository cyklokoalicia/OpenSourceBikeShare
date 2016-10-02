<?php

namespace BikeShare\Providers;

use BikeShare\Http\Services\AppConfig;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{

    protected $providers = [
        \Lanin\ApiDebugger\DebuggerServiceProvider::class,
        \Barryvdh\Debugbar\ServiceProvider::class,
        \Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider::class,
    ];
    protected $aliases = [
        'Debugger' => \Lanin\ApiDebugger\DebuggerFacade::class,
        'Debugbar' => \Barryvdh\Debugbar\Facade::class,
    ];
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
        if ($this->app->environment('local')) {
            if (! empty($this->providers)) {
                foreach ($this->providers as $provider) {
                    $this->app->register($provider);
                }
            }

            if (! empty($this->aliases)) {
                foreach ($this->aliases as $alias => $facade) {
                    $this->app->alias($alias, $facade);
                }
            }
        } elseif ($this->app->environment('production')) {
            // only for production
        }

        $this->app->singleton('AppConfig', function ($app) {
            return new AppConfig($app->config['bike-share']);
        });
    }
}
