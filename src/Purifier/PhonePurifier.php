<?php

declare(strict_types=1);

namespace BikeShare\Purifier;

class PhonePurifier implements PhonePurifierInterface
{
    /**
     * @param string[] $countryCodes ISO 3166-1 alpha-2 country codes
     */
    public function __construct(
        private readonly \libphonenumber\PhoneNumberUtil $phoneNumberUtil,
        private readonly array $countryCodes
    ) {
    }

    public function purify(string $phoneNumber): string
    {
        $phoneNumber = trim($phoneNumber);

        $parsed = $this->parse($phoneNumber);
        if ($parsed !== null) {
            return ltrim(
                $this->phoneNumberUtil->format($parsed, \libphonenumber\PhoneNumberFormat::E164),
                '+'
            );
        }

        return preg_replace('/\D+/', '', $phoneNumber) ?? '';
    }

    public function isValid(string $phoneNumber): bool
    {
        return $this->parse($phoneNumber) !== null;
    }

    private function parse(string $phoneNumber): ?\libphonenumber\PhoneNumber
    {
        foreach ($this->countryCodes as $region) {
            try {
                $number = $this->phoneNumberUtil->parse($phoneNumber, $region);
                $regionCode = $this->phoneNumberUtil->getRegionCodeForNumber($number);
                if (\in_array($regionCode, $this->countryCodes, true) &&
                    $this->phoneNumberUtil->isValidNumberForRegion($number, $regionCode)
                ) {
                    return $number;
                }
            } catch (\libphonenumber\NumberParseException) {
                // try next region
            }
        }

        try {
            $number = $this->phoneNumberUtil->parse($phoneNumber, \libphonenumber\PhoneNumberUtil::UNKNOWN_REGION);
            $regionCode = $this->phoneNumberUtil->getRegionCodeForNumber($number);
            if (\in_array($regionCode, $this->countryCodes, true) &&
                $this->phoneNumberUtil->isValidNumberForRegion($number, $regionCode)
            ) {
                return $number;
            }
        } catch (\libphonenumber\NumberParseException) {
        }

        return null;
    }
}
