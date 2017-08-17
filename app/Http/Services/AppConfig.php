<?php
namespace BikeShare\Http\Services;

class AppConfig
{
    private $prefix;

    private $app;

    public function __construct($app, $prefix='bike-share')
    {
        $this->app = $app;
        $this->prefix = $prefix;
    }

    public function getSystemName()
    {
        return $this->get('system.name');
    }


    public function getSystemRules()
    {
        return $this->get('system.rules');
    }


    public function isCreditEnabled()
    {
        return $this->get('credit.enabled');
    }


    public function isStackBikeEnabled()
    {
        return $this->get('stack_bike');
    }

    public function isStackWatchEnabled()
    {
        return $this->get('stack_watch');
    }

    public function isSmsEnabled()
    {
        return $this->get('sms.enabled');
    }


    public function getRegistrationLimits()
    {
        return $this->get('limits.registration');
    }


    public function getMinCredit()
    {
        return $this->get('credit.min');
    }


    public function getRentCredit()
    {
        return $this->get('credit.rent');
    }


    public function getLongRentCredit()
    {
        return $this->get('credit.long_rental');
    }


    public function getRequiredCredit()
    {
        return $this->getMinCredit() + $this->getRentCredit() + $this->getLongRentCredit();
    }


    public function getCreditCurrency()
    {
        return $this->get('credit.currency');
    }


    public function getPriceCycle()
    {
        return $this->get('credit.price_cycle');
    }


    public function getCreditLongRental()
    {
        return $this->get('credit.long_rental');
    }


    public function getWatchersLongRental()
    {
        return $this->get('watches.long_rental');
    }


    public function getFlatPriceCycle()
    {
        return $this->get('watches.flat_price_cycle');
    }


    public function getDoublePriceCycle()
    {
        return $this->get('watches.double_price_cycle');
    }


    public function getDoublePriceCycleCap()
    {
        return $this->get('watches.double_price_cycle_cap');
    }


    public function getFreeTime()
    {
        return $this->get('watches.free_time');
    }


    public function getTimeToMany()
    {
        return $this->get('watches.time_too_many');
    }

    public function getNumberToMany()
    {
        return $this->get('watches.number_too_many');
    }


    public function isNotifyUser()
    {
        return $this->get('notify_user');
    }

    private function get($key)
    {
        $key = $this->prefix . '.' . $key;
        return $this->app->config->get($key);
    }
}
