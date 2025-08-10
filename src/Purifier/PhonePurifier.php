<?php

declare(strict_types=1);

namespace BikeShare\Purifier;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class PhonePurifier implements PhonePurifierInterface
{
    /**
     * @param string[] $countryCodes ISO 3166-1 alpha-2 country codes
     */
    public function __construct(
        private readonly PhoneNumberUtil $phoneNumberUtil,
        private readonly array $countryCodes
    ) {
    }

    public function purify(string $phoneNumber): string
    {
        $phoneNumber = trim($phoneNumber);

        $parsed = $this->parse($phoneNumber);
        if ($parsed !== null) {
            return ltrim(
                $this->phoneNumberUtil->format($parsed, PhoneNumberFormat::E164),
                '+'
            );
        }

        throw new \InvalidArgumentException('Invalid phone number');
    }

    public function isValid(string $phoneNumber): bool
    {
        return $this->parse($phoneNumber) !== null;
    }

    private function parse(string $phoneNumber): ?PhoneNumber
    {
        $regions = array_merge(
            $this->countryCodes,
            [PhoneNumberUtil::UNKNOWN_REGION]
        );
        foreach ($regions as $region) {
            try {
                $number = $this->phoneNumberUtil->parse($phoneNumber, $region);
                $regionCode = $this->phoneNumberUtil->getRegionCodeForNumber($number);
                if (
                    \in_array($regionCode, $this->countryCodes, true)
                    && $this->phoneNumberUtil->isValidNumberForRegion($number, $regionCode)
                ) {
                    return $number;
                }
            } catch (NumberParseException) {
                // try next region
            }
        }

        return null;
    }
}
