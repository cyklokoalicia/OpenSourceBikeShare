<?php

namespace BikeShare\Providers;

use BikeShare\Http\Services\Sms\EuroSms;
use BikeShare\Http\Services\Sms\Receivers\EuroSmsRequest;
use BikeShare\Http\Services\Sms\Receivers\SmsRequestContract;
use Illuminate\Support\ServiceProvider;

class SmsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(EuroSms::class, function () {
            $config = $this->app['config']['services.euroSms'];
            return new EuroSms($config['id'], $config['key'], $config['senderNumber']);
        });

        $this->app->bind(SmsRequestContract::class, function(){
            switch ($this->app['config']['bike-share.sms.connector']){
                case 'euroSms':
                case 'twilio':
                case 'log':
                case 'null':
                default:
                    return new EuroSmsRequest();
                    break;
            }
        });
    }
}
