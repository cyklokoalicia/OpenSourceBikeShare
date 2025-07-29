<?php

namespace BikeShare\Purifier;

interface PhonePurifierInterface
{
    public function purify(string $phoneNumber);
}
