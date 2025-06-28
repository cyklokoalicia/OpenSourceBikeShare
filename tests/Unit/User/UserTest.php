<?php

namespace BikeShare\Test\Unit\User;

use BikeShare\Db\DbInterface;
use BikeShare\Db\DbResultInterface;
use BikeShare\User\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testFindUserIdByNumber()
    {
        $userNumber = '12345';
        $expectedUserId = 1;

        $dbFoundUserResult = $this->createMock(DbResultInterface::class);
        $dbFoundUserResult->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);
        $dbFoundUserResult->expects($this->once())
            ->method('fetchAssoc')
            ->willReturn(['userId' => $expectedUserId]);

        $dbNotFoundUserResult = $this->createMock(DbResultInterface::class);
        $dbNotFoundUserResult->expects($this->once())
            ->method('rowCount')
            ->willReturn(0);

        $dbMock = $this->createMock(DbInterface::class);
        $dbMock->expects($this->exactly(2))
            ->method('query')
            ->with('SELECT userId FROM users WHERE number = :number', ['number' => $userNumber])
            ->willReturnOnConsecutiveCalls(
                $dbFoundUserResult,
                $dbNotFoundUserResult
            );
        $user = new User($dbMock);
        $this->assertEquals($expectedUserId, $user->findUserIdByNumber($userNumber));
        $this->assertNull($user->findUserIdByNumber($userNumber));
    }

    public function testFindPhoneNumber()
    {
        $userId = 1;
        $expectedPhoneNumber = '123-456-7890';

        $dbFoundUserResult = $this->createMock(DbResultInterface::class);
        $dbFoundUserResult->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);
        $dbFoundUserResult->expects($this->once())
            ->method('fetchAssoc')
            ->willReturn(['number' => $expectedPhoneNumber]);

        $dbNotFoundUserResult = $this->createMock(DbResultInterface::class);
        $dbNotFoundUserResult->expects($this->once())
            ->method('rowCount')
            ->willReturn(0);

        $dbMock = $this->createMock(DbInterface::class);
        $dbMock->expects($this->exactly(2))
            ->method('query')
            ->with('SELECT number FROM users WHERE userId = :userId', ['userId' => $userId])
            ->willReturnOnConsecutiveCalls(
                $dbFoundUserResult,
                $dbNotFoundUserResult
            );
        $user = new User($dbMock);
        $this->assertEquals($expectedPhoneNumber, $user->findPhoneNumber($userId));
        $this->assertNull($user->findPhoneNumber($userId));
    }

    public function testFindCity()
    {
        $userId = 1;
        $expectedCity = 'Springfield';

        $dbFoundUserResult = $this->createMock(DbResultInterface::class);
        $dbFoundUserResult->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);
        $dbFoundUserResult->expects($this->once())
            ->method('fetchAssoc')
            ->willReturn(['city' => $expectedCity]);

        $dbNotFoundUserResult = $this->createMock(DbResultInterface::class);
        $dbNotFoundUserResult->expects($this->once())
            ->method('rowCount')
            ->willReturn(0);

        $dbMock = $this->createMock(DbInterface::class);
        $dbMock->expects($this->exactly(2))
            ->method('query')
            ->with('SELECT city FROM users WHERE userId = :userId', ['userId' => $userId])
            ->willReturnOnConsecutiveCalls(
                $dbFoundUserResult,
                $dbNotFoundUserResult
            );
        $user = new User($dbMock);
        $this->assertEquals($expectedCity, $user->findCity($userId));
        $this->assertNull($user->findCity($userId));
    }

    public function testFindUserName()
    {
        $userId = 1;
        $expectedUserName = 'JohnDoe';

        $dbFoundUserResult = $this->createMock(DbResultInterface::class);
        $dbFoundUserResult->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);
        $dbFoundUserResult->expects($this->once())
            ->method('fetchAssoc')
            ->willReturn(['userName' => $expectedUserName]);

        $dbNotFoundUserResult = $this->createMock(DbResultInterface::class);
        $dbNotFoundUserResult->expects($this->once())
            ->method('rowCount')
            ->willReturn(0);

        $dbMock = $this->createMock(DbInterface::class);
        $dbMock->expects($this->exactly(2))
            ->method('query')
            ->with('SELECT userName FROM users WHERE userId = :userId', ['userId' => $userId])
            ->willReturnOnConsecutiveCalls(
                $dbFoundUserResult,
                $dbNotFoundUserResult
            );
        $user = new User($dbMock);
        $this->assertEquals($expectedUserName, $user->findUserName($userId));
        $this->assertNull($user->findUserName($userId));
    }

    public function testFindPrivileges()
    {
        $userId = 1;
        $expectedPrivileges = '7';

        $dbFoundUserResult = $this->createMock(DbResultInterface::class);
        $dbFoundUserResult->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);
        $dbFoundUserResult->expects($this->once())
            ->method('fetchAssoc')
            ->willReturn(['privileges' => $expectedPrivileges]);

        $dbNotFoundUserResult = $this->createMock(DbResultInterface::class);
        $dbNotFoundUserResult->expects($this->once())
            ->method('rowCount')
            ->willReturn(0);

        $dbMock = $this->createMock(DbInterface::class);
        $dbMock->expects($this->exactly(2))
            ->method('query')
            ->with('SELECT privileges FROM users WHERE userId = :userId', ['userId' => $userId])
            ->willReturnOnConsecutiveCalls(
                $dbFoundUserResult,
                $dbNotFoundUserResult
            );

        $user = new User($dbMock);
        $this->assertEquals($expectedPrivileges, $user->findPrivileges($userId));
        $this->assertNull($user->findPrivileges($userId));
    }
}
