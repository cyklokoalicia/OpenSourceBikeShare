<?php

declare(strict_types=1);

namespace BikeShare\Purifier;

class PhonePurifier implements PhonePurifierInterface
{
    public function __construct(private string $countryCode)
    {
    }

    public function purify($phoneNumber)
    {
        $phoneNumber = preg_replace('/[^\d]/', '', $phoneNumber);
        if (str_starts_with($phoneNumber, '0')) {
            $phoneNumber = substr($phoneNumber, 1);
        }

        if (substr($phoneNumber, 0, 3) != $this->countryCode) {
            $phoneNumber = $this->countryCode . $phoneNumber;
        }

        return $phoneNumber;
    }
}
