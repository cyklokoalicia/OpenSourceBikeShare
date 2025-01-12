<?php

declare(strict_types=1);

namespace Test\BikeShare\Unit\Credit;

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
        $isEnabled,
        $creditCurrency,
        $minRequiredCredit,
        $rentalFee,
        $priceCycle,
        $longRentalFee,
        $limitIncreaseFee,
        $violationFee,
        $expectedMinRequiredCredit,
        $expectedException = null
    ) {
        if (!is_null($expectedException)) {
            $this->expectException($expectedException);
        }
        $creditSystem = new CreditSystem(
            $isEnabled,
            $creditCurrency,
            $minRequiredCredit,
            $rentalFee,
            $priceCycle,
            $longRentalFee,
            $limitIncreaseFee,
            $violationFee,
            $this->createMock(DbInterface::class)
        );
        $this->assertEquals($isEnabled, $creditSystem->isEnabled());
        $this->assertEquals($creditCurrency, $creditSystem->getCreditCurrency());
        $this->assertEquals($expectedMinRequiredCredit, $creditSystem->getMinRequiredCredit());
        $this->assertEquals($rentalFee, $creditSystem->getRentalFee());
        $this->assertEquals($priceCycle, $creditSystem->getPriceCycle());
        $this->assertEquals($longRentalFee, $creditSystem->getLongRentalFee());
        $this->assertEquals($limitIncreaseFee, $creditSystem->getLimitIncreaseFee());
        $this->assertEquals($violationFee, $creditSystem->getViolationFee());
    }

    public function constructorDataProvider()
    {
        yield 'disabled configuration' => [
            'isEnabled' => false,
            'creditCurrency' => '€',
            'minRequiredCredit' => 9,
            'rentalFee' => 2,
            'priceCycle' => 0,
            'longRentalFee' => 5,
            'limitIncreaseFee' => 10,
            'violationFee' => 5,
            'expectedMinRequiredCredit' => 9,
            'expectedException' => \RuntimeException::class,
        ];
        yield 'full configuration' => [
            'isEnabled' => true,
            'creditCurrency' => '$',
            'minRequiredCredit' => 12,
            'rentalFee' => 3,
            'priceCycle' => 1,
            'longRentalFee' => 6,
            'limitIncreaseFee' => 11,
            'violationFee' => 6,
            'expectedMinRequiredCredit' => 21,
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
            ->with('SELECT credit FROM credit WHERE userId = :userId', ['userId' => $userId])
            ->willReturn($dbResult);

        $creditSystem = new CreditSystem(
            true, //isEnabled
            '€', //creditCurrency
            9, //minRequiredCredit
            2, //rentalFee
            0, //priceCycle
            5, //longRentalFee
            10, //limitIncreaseFee
            5, //violationFee
            $db
        );

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
            ->with('SELECT credit FROM credit WHERE userId = :userId', ['userId' => $userId])
            ->willReturn($dbResult);

        $creditSystem = new CreditSystem(
            true, //isEnabled
            '€', //creditCurrency
            9, //minRequiredCredit
            2, //rentalFee
            0, //priceCycle
            5, //longRentalFee
            10, //limitIncreaseFee
            5, //violationFee
            $db
        );

        $this->assertEquals(0, $creditSystem->getUserCredit($userId));
    }
}
