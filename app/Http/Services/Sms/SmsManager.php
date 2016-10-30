<?php
namespace BikeShare\Http\Services\Sms;

use BikeShare\Http\Services\Sms\Drivers\EuroSmsDriver;
use BikeShare\Http\Services\Sms\Drivers\LogSmsDriver;
use BikeShare\Http\Services\Sms\Drivers\NullSmsDriver;
use BikeShare\Http\Services\Sms\Drivers\SmsService;
use InvalidArgumentException;

class SmsManager
{

    /**
     * The application instance.
     *
     * @var \Illuminate\Foundation\Application
     */
    protected $app;

    /**
     * The array of resolved sms drivers.
     *
     * @var array
     */
    protected $drivers = [];


    /**
     * Create a new manager instance.
     *
     * @param  \Illuminate\Foundation\Application $app
     *
     * @return void
     */
    public function __construct($app)
    {
        $this->app = $app;
    }


    /**
     * Resolve the given store.
     *
     * @param  string $name
     *
     * @return \Illuminate\Contracts\Broadcasting\Broadcaster
     *
     * @throws \InvalidArgumentException
     */
    protected function resolve($name)
    {
        $config = $this->getConfig($name);

        if (is_null($config)) {
            throw new InvalidArgumentException("Sms gateway [{$name}] is not defined.");
        }

        $driverMethod = 'create' . ucfirst($config['driver']) . 'Driver';

        if (method_exists($this, $driverMethod)) {
            return $this->{$driverMethod}($config);
        } else {
            throw new InvalidArgumentException("Driver [{$config['driver']}] is not supported.");
        }

    }


    /**
     * Get the connection configuration.
     *
     * @param  string $name
     *
     * @return array
     */
    protected function getConfig($name)
    {
        return $this->app['config']["bike-share.sms.connections.{$name}"];
    }


    /**
     * Get the default driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->app['config']['bike-share.sms.connector'];
    }


    /**
     * Get a driver instance.
     *
     * @param  string $name
     *
     * @return mixed
     */
    public function driver($name = null)
    {
        $name = $name ?: $this->getDefaultDriver();

        return $this->drivers[$name] = $this->get($name);
    }


    /**
     * Attempt to get the connection from the local cache.
     *
     * @param  string $name
     *
     * @return \Illuminate\Contracts\Broadcasting\Broadcaster
     */
    protected function get($name)
    {
        return isset($this->drivers[$name]) ? $this->drivers[$name] : $this->resolve($name);
    }


    /**
     * Create an instance of the driver.
     *
     * @param  array $config
     *
     * @return SmsService
     */
    protected function createLogDriver(array $config)
    {
        return new LogSmsDriver($this->app->make('Psr\Log\LoggerInterface'));
    }


    /**
     * Create an instance of the driver.
     *
     * @param  array $config
     *
     * @return SmsService
     */
    protected function createEuroSmsDriver(array $config)
    {
        return new EuroSmsDriver(new EuroSms($config['id'], $config['key'], $config['senderNumber']));
    }


    /**
     * Create an instance of the driver.
     *
     * @param  array $config
     *
     * @return SmsService
     */
    protected function createNullDriver(array $config)
    {
        return new NullSmsDriver();
    }
}
