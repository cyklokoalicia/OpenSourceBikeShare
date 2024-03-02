<?php

namespace Test\BikeShare\User;

use BikeShare\Db\DbInterface;
use BikeShare\User\User;
use PHPUnit\Framework\TestCase;
use Test\BikeShare\MysqliResult;

class UserTest extends TestCase
{
    public function testFindUserIdByNumberReturnsUserId()
    {
        $userNumber = '12345';
        $expectedUserId = 1;
        $dbMock = $this->createMock(DbInterface::class);
        $dbMock->expects($this->exactly(2))
            ->method('query')
            ->with("SELECT userId FROM users WHERE userNumber='{$userNumber}'")
            ->willReturnOnConsecutiveCalls(
                new MysqliResult(1, [['userId' => $expectedUserId]]),
                new MysqliResult(0, [])
            );
        $user = new User($dbMock);
        $this->assertEquals($expectedUserId, $user->findUserIdByNumber($userNumber));
        $this->assertNull($user->findUserIdByNumber($userNumber));
    }

    public function testFindPhoneNumberReturnsPhoneNumber()
    {
        $userId = 1;
        $expectedPhoneNumber = '123-456-7890';
        $dbMock = $this->createMock(DbInterface::class);
        $dbMock->expects($this->exactly(2))
            ->method('query')
            ->with("SELECT number FROM users WHERE userId='{$userId}'")
            ->willReturnOnConsecutiveCalls(
                new MysqliResult(1, [['number' => $expectedPhoneNumber]]),
                new MysqliResult(0, [])
            );
        $user = new User($dbMock);
        $this->assertEquals($expectedPhoneNumber, $user->findPhoneNumber($userId));
        $this->assertNull($user->findPhoneNumber($userId));
    }

    public function testFindCityReturnsCity()
    {
        $userId = 1;
        $expectedCity = 'Springfield';
        $dbMock = $this->createMock(DbInterface::class);
        $dbMock->expects($this->exactly(2))
            ->method('query')
            ->with("SELECT city FROM users WHERE userId='{$userId}'")
            ->willReturnOnConsecutiveCalls(
                new MysqliResult(1, [['city' => $expectedCity]]),
                new MysqliResult(0, [])
            );
        $user = new User($dbMock);
        $this->assertEquals($expectedCity, $user->findCity($userId));
        $this->assertNull($user->findCity($userId));
    }

    public function testFindUserNameReturnsUserName()
    {
        $userId = 1;
        $expectedUserName = 'JohnDoe';
        $dbMock = $this->createMock(DbInterface::class);
        $dbMock->expects($this->exactly(2))
            ->method('query')
            ->with("SELECT userName FROM users WHERE userId='{$userId}'")
            ->willReturnOnConsecutiveCalls(
                new MysqliResult(1, [['userName' => $expectedUserName]]),
                new MysqliResult(0, [])
            );
        $user = new User($dbMock);
        $this->assertEquals($expectedUserName, $user->findUserName($userId));
        $this->assertNull($user->findUserName($userId));
    }

    public function testFindPrivilegesReturnsPrivileges()
    {
        $userId = 1;
        $expectedPrivileges = '7';
        $dbMock = $this->createMock(DbInterface::class);
        $dbMock->expects($this->exactly(2))
            ->method('query')
            ->with("SELECT privileges FROM users WHERE userId='{$userId}'")
            ->willReturnOnConsecutiveCalls(
                new MysqliResult(1, [['privileges' => $expectedPrivileges]]),
                new MysqliResult(0, [])
            );

        $user = new User($dbMock);
        $this->assertEquals($expectedPrivileges, $user->findPrivileges($userId));
        $this->assertNull($user->findPrivileges($userId));
    }
}
