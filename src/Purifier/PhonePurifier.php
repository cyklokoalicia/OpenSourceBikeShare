<?php

namespace BikeShare\Purifier;

class PhonePurifier implements PhonePurifierInterface
{
    /**
     * @var string
     */
    private $countryCode;

    public function __construct(
        $countryCode
    ) {
        $this->countryCode = $countryCode;
    }

    public function purify($phoneNumber)
    {
        $phoneNumber = preg_replace('/[^\d]/', '', $phoneNumber);
        if (substr($phoneNumber, 0, 1) == '0') {
            $phoneNumber = substr($phoneNumber, 1);
        }

        if (substr($phoneNumber, 0, 3) != $this->countryCode) {
            $phoneNumber = $this->countryCode . $phoneNumber;
        }

        return $phoneNumber;
    }
}
