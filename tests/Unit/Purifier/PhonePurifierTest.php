<?php

declare(strict_types=1);

namespace Test\BikeShare\Unit\Purifier;

use BikeShare\Purifier\PhonePurifier;
use PHPUnit\Framework\TestCase;

class PhonePurifierTest extends TestCase
{
    /**
     * @dataProvider purifyDataProvider
     */
    public function testPurify(
        $phoneNumber,
        $countryCode,
        $expectedPhoneNumber
    ) {
        $purifier = new PhonePurifier($countryCode);
        $this->assertEquals($expectedPhoneNumber, $purifier->purify($phoneNumber));
    }

    public function purifyDataProvider()
    {
        yield 'default' => [
            'phoneNumber' => '+1234567890',
            'countryCode' => '123',
            'expectedPhoneNumber' => '1234567890'
        ];
        yield 'restricted symbols remove' => [
            'phoneNumber' => '+421 123-456-78/90.',
            'countryCode' => '421',
            'expectedPhoneNumber' => '4211234567890'
        ];
        yield 'letters symbols do not remove' => [
            'phoneNumber' => '+421 123-456-78/90abcdefghijklmnopqrstuvwxyz',
            'countryCode' => '421',
            'expectedPhoneNumber' => '4211234567890'
        ];
        yield 'without code' => [
            'phoneNumber' => '0123-456-78/90',
            'countryCode' => '421',
            'expectedPhoneNumber' => '4211234567890'
        ];
        yield 'with 3 symbol code and with 0' => [
            'phoneNumber' => '0421123-456-78/90',
            'countryCode' => '421',
            'expectedPhoneNumber' => '4211234567890'
        ];
        #is it correct??? maybe code can be less or more than 3 symbols???
        yield 'with 2 symbol code and with 0' => [
            'phoneNumber' => '0123-456-78/90',
            'countryCode' => '12',
            'expectedPhoneNumber' => '121234567890'
        ];
    }
}
