<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\Purifier;

use BikeShare\Purifier\PhonePurifier;
use libphonenumber\PhoneNumberUtil;
use PHPUnit\Framework\TestCase;

class PhonePurifierTest extends TestCase
{
    /**
     * @dataProvider purifyDataProvider
     */
    public function testPurify(
        $phoneNumber,
        $countryCode,
        $expectedPhoneNumber,
        $expectedException = null,
    ) {
        if ($expectedException) {
            $this->expectException($expectedException);
        }
        $purifier = new PhonePurifier(PhoneNumberUtil::getInstance(), [$countryCode]);
        $this->assertEquals($expectedPhoneNumber, $purifier->purify($phoneNumber));
    }

    public function purifyDataProvider()
    {
        yield 'default' => [
            'phoneNumber' => '+421 903-123-456',
            'countryCode' => 'SK',
            'expectedPhoneNumber' => '421903123456',
            'expectedException' => null,
        ];
        yield 'local number without prefix' => [
            'phoneNumber' => '0903 123 456',
            'countryCode' => 'SK',
            'expectedPhoneNumber' => '421903123456',
            'expectedException' => null,
        ];
        yield 'international for another region' => [
            'phoneNumber' => '+33123456789',
            'countryCode' => 'SK',
            'expectedPhoneNumber' => '',
            'expectedException' => \InvalidArgumentException::class,
        ];
    }

    public function testIsValid(): void
    {
        $purifier = new PhonePurifier(PhoneNumberUtil::getInstance(), ['SK', 'CZ']);
        $this->assertTrue($purifier->isValid('421903123456'));
        $this->assertTrue($purifier->isValid('420601123456'));
        $this->assertFalse($purifier->isValid('33123456789'));
    }
}
