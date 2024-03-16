<?php

namespace Test\BikeShare\Credit;

use BikeShare\Credit\CreditSystem;
use BikeShare\Db\DbInterface;
use BikeShare\Db\DbResultInterface;
use PHPUnit\Framework\TestCase;

class CreditSystemTest extends TestCase
{
    /**
     * @dataProvider constructorDataProvider
     */
    public function testConstructor(
        $configuration,
        $expectedIsEnabled,
        $expectedCreditCurrency,
        $expectedMinRequiredCredit,
        $expectedRentalFee,
        $expectedPriceCycle,
        $expectedLongRentalFee,
        $expectedLimitIncreaseFee,
        $expectedViolationFee
    ) {
        $creditSystem = new CreditSystem($configuration, $this->createMock(DbInterface::class));
        $this->assertEquals($expectedIsEnabled, $creditSystem->isEnabled());
        $this->assertEquals($expectedCreditCurrency, $creditSystem->getCreditCurrency());
        $this->assertEquals($expectedMinRequiredCredit, $creditSystem->getMinRequiredCredit());
        $this->assertEquals($expectedRentalFee, $creditSystem->getRentalFee());
        $this->assertEquals($expectedPriceCycle, $creditSystem->getPriceCycle());
        $this->assertEquals($expectedLongRentalFee, $creditSystem->getLongRentalFee());
        $this->assertEquals($expectedLimitIncreaseFee, $creditSystem->getLimitIncreaseFee());
        $this->assertEquals($expectedViolationFee, $creditSystem->getViolationFee());
    }

    public function constructorDataProvider()
    {
        yield 'empty configuration' => [
            'configuration' => [],
            'expectedIsEnabled' => false,
            'expectedCreditCurrency' => '€',
            'expectedMinRequiredCredit' => 9,
            'expectedRentalFee' => 2,
            'expectedPriceCycle' => 0,
            'expectedLongRentalFee' => 5,
            'expectedLimitIncreaseFee' => 10,
            'expectedViolationFee' => 5
        ];
        yield 'full configuration' => [
            'configuration' => [
                'enabled' => true,
                'currency' => '$',
                'min' => 3,
                'rent' => 3,
                'pricecycle' => 1,
                'longrental' => 6,
                'limitincrease' => 11,
                'violation' => 6
            ],
            'expectedIsEnabled' => true,
            'expectedCreditCurrency' => '$',
            'expectedMinRequiredCredit' => 12,
            'expectedRentalFee' => 3,
            'expectedPriceCycle' => 1,
            'expectedLongRentalFee' => 6,
            'expectedLimitIncreaseFee' => 11,
            'expectedViolationFee' => 6
        ];
    }

    public function testGetUserCredit()
    {
        $userId = 1;
        $dbResult = $this->createMock(DbResultInterface::class);
        $dbResult->expects($this->once())
            ->method('fetchAssoc')
            ->willReturn(['credit' => 5]);
        $dbResult->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $db = $this->createMock(DbInterface::class);
        $db->expects($this->once())
            ->method('query')
            ->with("SELECT credit FROM users WHERE id = '$userId'")
            ->willReturn($dbResult);

        $creditSystem = new CreditSystem(['isEnabled' => true], $db);

        $this->assertEquals(5, $creditSystem->getUserCredit($userId));
    }
    public function testGetUserCreditNotFoundUser()
    {
        $userId = 1;

        $dbResult = $this->createMock(DbResultInterface::class);
        $dbResult->expects($this->once())
            ->method('rowCount')
            ->willReturn(0);

        $db = $this->createMock(DbInterface::class);
        $db->expects($this->once())
            ->method('query')
            ->with("SELECT credit FROM users WHERE id = '$userId'")
            ->willReturn($dbResult);

        $creditSystem = new CreditSystem(['isEnabled' => true], $db);

        $this->assertEquals(0, $creditSystem->getUserCredit($userId));
    }
}
