<?php

namespace BikeShare\Rent;

class RentSystemSms extends AbstractRentSystem implements RentSystemInterface
{
    private $number;

    public function rentBike($number, $bikeId, $force = false)
    {
        global $user;

        $this->number = $number;
        $userId = $user->findUserIdByNumber($number);

        return parent::rentBike($userId, $bikeId, $force);
    }

    public function returnBike($number, $bikeId, $standName, $note = '', $force = false)
    {
        global $db, $user;

        $this->number = $number;
        $userId = $user->findUserIdByNumber($number);
        if (preg_match("/return[\s,\.]+[0-9]+[\s,\.]+[a-zA-Z0-9]+[\s,\.]+(.*)/i", $note, $matches)) {
            $note = $db->escape(trim($matches[1]));
        }

        return parent::returnBike($userId, $bikeId, $standName, $note, $force);
    }

    protected function getRentSystemType() {
        return 'sms';
    }

    protected function response($message, $error = 0, $additional = '', $log = 1)
    {
        global $smsSender;
        
        $smsSender->send($this->number, $message);
    }
}