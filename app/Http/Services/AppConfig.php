<?php
namespace BikeShare\Http\Services;

class AppConfig
{

    protected $config;


    public function __construct($config)
    {
        $this->config = $config;
    }


    public function getSystemName()
    {
        return $this->config['system']['name'];
    }


    public function getSystemRules()
    {
        return $this->config['system']['rules'];
    }


    public function isCreditEnabled()
    {
        return $this->config['credit']['enabled'];
    }


    public function isStackBikeEnabled()
    {
        return $this->config['stack_bike'];
    }


    public function isSmsEnabled()
    {
        return $this->config['sms']['enabled'];
    }


    public function getRegistrationLimits()
    {
        return $this->config['limits']['registration'];
    }


    public function getMinCredit()
    {
        return $this->config['credit']['min'];
    }


    public function getRentCredit()
    {
        return $this->config['credit']['rent'];
    }


    public function getLongRentCredit()
    {
        return $this->config['credit']['long_rental'];
    }


    public function getRequiredCredit()
    {
        return $this->getMinCredit() + $this->getRentCredit() + $this->getLongRentCredit();
    }


    public function getCreditCurrency()
    {
        return $this->config['credit']['currency'];
    }


    public function getPriceCycle()
    {
        return $this->config['credit']['price_cycle'];
    }


    public function getCreditLongRental()
    {
        return $this->config['credit']['long_rental'];
    }


    public function getWatchersLongRental()
    {
        return $this->config['watches']['long_rental'];
    }


    public function getFlatPriceCycle()
    {
        return $this->config['watches']['flat_price_cycle'];
    }


    public function getDoublePriceCycle()
    {
        return $this->config['watches']['double_price_cycle'];
    }


    public function getDoublePriceCycleCap()
    {
        return $this->config['watches']['double_price_cycle_cap'];
    }


    public function getFreeTime()
    {
        return $this->config['watches']['free_time'];
    }


    public function getTimeToMany()
    {
        return $this->config['watches']['time_too_many'];
    }

    public function getNumberToMany()
    {
        return $this->config['watches']['number_too_many'];
    }


    public function isNotifyUser()
    {
        return $this->config['notify_user'];
    }
}
