<?php

namespace Test\BikeShare\Authentication;

use BikeShare\Authentication\Auth;
use BikeShare\Db\DbInterface;
use BikeShare\Db\DbResultInterface;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase
{
    use PHPMock;

    private const SESSION_EXPIRATION = 86400 * 14;
    /**
     * @var DbInterface|MockObject
     */
    private $db;
    /**
     * @var Auth
     */
    private $auth;

    public function setUp(): void
    {
        $this->db = $this->createMock(DbInterface::class);
        $this->auth = new Auth(
            $this->db
        );
    }

    protected function tearDown(): void
    {
        unset(
            $this->db,
            $this->auth
        );
    }


    /**
     * @dataProvider getUserIdDataProvider
     */
    public function testGetUserId(
        $cookieValue = null,
        $expectedUserId = 0
    ) {
        if (!is_null($cookieValue)) {
            $_COOKIE["loguserid"] = $cookieValue;
            $this->db->expects($this->once())
                ->method('escape')
                ->with($cookieValue)
                ->willReturn($cookieValue);
        }
        $this->assertEquals($expectedUserId, $this->auth->getUserId());
    }

    public function getUserIdDataProvider()
    {

        yield 'no cookie' => [
            'cookieValue' => null,
            'expectedUserId' => 0,
        ];
        yield 'empty cookie' => [
            'cookieValue' => '',
            'expectedUserId' => 0,
        ];
        yield 'not a number' => [
            'cookieValue' => 'not a number',
            'expectedUserId' => 0,
        ];
        yield 'number' => [
            'cookieValue' => '123',
            'expectedUserId' => 123,
        ];
        yield 'sql injection' => [
            'cookieValue' => '123; DROP TABLE users',
            'expectedUserId' => 123,
        ];
    }

    /**
     * @dataProvider getSessionIdDataProvider
     */
    public function testGetSessionId(
        $cookieValue = null,
        $expectedSessionId = 0
    ) {
        if (!is_null($cookieValue)) {
            $_COOKIE["logsession"] = $cookieValue;
            $this->db->expects($this->once())
                ->method('escape')
                ->with($cookieValue)
                ->willReturn(str_replace(';', '\;', $cookieValue));# just an example for test
        }
        $this->assertEquals($expectedSessionId, $this->auth->getSessionId());
    }

    public function getSessionIdDataProvider()
    {

        yield 'no cookie' => [
            'cookieValue' => null,
            'expectedSessionId' => '',
        ];
        yield 'empty cookie' => [
            'cookieValue' => '',
            'expectedSessionId' => '',
        ];
        yield 'not a number' => [
            'cookieValue' => 'not a number',
            'expectedSessionId' => 'not a number',
        ];
        yield 'number' => [
            'cookieValue' => '123',
            'expectedSessionId' => '123',
        ];
        yield 'sql injection' => [
            'cookieValue' => '123; DROP TABLE users',
            'expectedSessionId' => '123\; DROP TABLE users',
        ];
    }


    public function testLogin()
    {
        $number = 'number';
        $password = 'password';
        $userId = '123';

        $this->getFunctionMock('BikeShare\Authentication', 'time')
            ->expects($this->exactly(2))
            ->willReturn(9999);

        $this->db->expects($this->exactly(2))
            ->method('escape')
            ->withConsecutive(
                [$number],
                [$password]
            )->willReturnOnConsecutiveCalls($number, $password);

        $sessionId = hash('sha256', $userId . $number . '9999');

        $foundUser = $this->createMock(DbResultInterface::class);
        $foundUser->expects($this->once())
            ->method('fetchAssoc')
            ->willReturn(['userId' => $userId]);
        $foundUser->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $this->db->expects($this->exactly(3))
            ->method('query')
            ->withConsecutive(
                ["SELECT userId FROM users WHERE number='$number' AND password=SHA2('$password',512)"],
                ["DELETE FROM sessions WHERE userId='{$userId}'"],
                ["INSERT INTO sessions SET userId='{$userId}',sessionId='{$sessionId}',timeStamp='1219599'"]
            )
            ->willReturnOnConsecutiveCalls(
                $foundUser,
                null,
                null
            );

        $this->getFunctionMock('BikeShare\Authentication', 'setcookie')
            ->expects($this->exactly(2))
            ->withConsecutive(
                ['loguserid', $userId, 1219599],
                ['logsession', $sessionId, 1219599]
            )
            ->willReturn(true);

        $this->getFunctionMock('BikeShare\Authentication', 'header')
            ->expects($this->exactly(3))
            ->withConsecutive(
                ['HTTP/1.1 302 Found'],
                ['Location: /'],
                ['Connection: close']
            );

        $this->auth->login($number, $password);
    }

    /**
     * @dataProvider isLoggedInDataProvider
     */
    public function testIsLoggedIn(
        $userId,
        $sessionId,
        $escapeCallParams,
        $escapeCallResults,
        $sessionFindResult,
        $expectedResult = false
    ) {
        if ($userId) {
            $_COOKIE["loguserid"] = $userId;
        } else {
            $_COOKIE["loguserid"] = null;
        }
        if ($sessionId) {
            $_COOKIE["logsession"] = $sessionId;
        } else {
            $_COOKIE["logsession"] = null;
        }

        $this->getFunctionMock('BikeShare\Authentication', 'time')
            ->expects(count($escapeCallParams) > 0 ? $this->once() : $this->never())
            ->willReturn(9999);

        $this->db
            ->expects($this->exactly(count($escapeCallParams)))
            ->method('escape')
            ->withConsecutive(...$escapeCallParams)
            ->willReturnOnConsecutiveCalls(...$escapeCallResults);

        $this->db
            ->expects(count($escapeCallParams) > 0 ? $this->once() : $this->never())
            ->method('query')
            ->with(
                "SELECT sessionId FROM sessions WHERE 
                           userId='$userId' AND sessionId='$sessionId' AND timeStamp>'9999'"
            )
            ->willReturn($sessionFindResult);

        $this->assertEquals($expectedResult, $this->auth->isLoggedIn());
    }

    public function isLoggedInDataProvider()
    {
        yield 'no user id' => [
            'userId' => 0,
            'sessionId' => '',
            'escapeCallParams' => [],
            'escapeCallResults' => [],
            'sessionFindResult' => $this->createMock(DbResultInterface::class),
            'expectedResult' => false,
        ];
        yield 'no session id' => [
            'userId' => 1,
            'sessionId' => '',
            'escapeCallParams' => [],
            'escapeCallResults' => [],
            'sessionFindResult' => $this->createMock(DbResultInterface::class),
            'expectedResult' => false,
        ];
        $sessionFindResult = $this->createMock(DbResultInterface::class);
        $sessionFindResult->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);
        yield 'user id and session id' => [
            'userId' => 1,
            'sessionId' => '123',
            'escapeCallParams' => [
                ['123'],
                [1],
            ],
            'escapeCallResults' => [
                '123',
                1,
            ],
            'sessionFindResult' => $sessionFindResult,
            'expectedResult' => true,
        ];
    }

    public function testLogout()
    {
        $userId = 1;
        $sessionId = '123';
        $_COOKIE["loguserid"] = $userId;
        $_COOKIE["logsession"] = $sessionId;
        $this->db->expects($this->exactly(4))
            ->method('escape')
            ->withConsecutive(
                [$sessionId],
                [$userId],
                [$userId],
                [$sessionId]
            )
            ->willReturnOnConsecutiveCalls(
                $sessionId,
                $userId,
                $userId,
                $sessionId
            );

        $this->getFunctionMock('BikeShare\Authentication', 'time')
            ->expects($this->exactly(3))
            ->willReturn(9999);

        $this->getFunctionMock('BikeShare\Authentication', 'setcookie')
            ->expects($this->exactly(2))
            ->withConsecutive(
                ['loguserid', '0', ['expires' => 6399, 'path' => '/']],
                ['logsession', '', ['expires' => 6399, 'path' => '/']]
            )
            ->willReturn(true);

        $this->getFunctionMock('BikeShare\Authentication', 'header')
            ->expects($this->exactly(3))
            ->withConsecutive(
                ['HTTP/1.1 302 Found'],
                ['Location: /'],
                ['Connection: close']
            );

        $sessionFindResult = $this->createMock(DbResultInterface::class);
        $sessionFindResult->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $this->db->expects($this->exactly(2))
            ->method('query')
            ->withConsecutive(
                ["SELECT sessionId FROM sessions WHERE 
                           userId='1' AND sessionId='123' AND timeStamp>'9999'"],
                ["DELETE FROM sessions WHERE userId='$userId' OR sessionId='$sessionId'"]
            )
            ->willReturnOnConsecutiveCalls(
                $sessionFindResult,
                null
            );

        $this->auth->logout();
    }

    public function testRefreshSession()
    {
        $userId = 1;
        $sessionId = '123';
        $_COOKIE["loguserid"] = $userId;
        $_COOKIE["logsession"] = $sessionId;
        $this->db->expects($this->exactly(4))
            ->method('escape')
            ->withConsecutive(
                [$sessionId],
                [$userId],
                [$userId],
                [$sessionId]
            )
            ->willReturnOnConsecutiveCalls(
                $sessionId,
                $userId,
                $userId,
                $sessionId
            );

        $this->getFunctionMock('BikeShare\Authentication', 'time')
            ->expects($this->exactly(4))
            ->willReturn(9999);

        $sessionFindResult = $this->createMock(DbResultInterface::class);
        $sessionFindResult->expects($this->exactly(2))
            ->method('rowCount')
            ->willReturn(1);

        $this->db->expects($this->exactly(4))
            ->method('query')
            ->withConsecutive(
                ["SELECT sessionId FROM sessions WHERE 
                           userId='1' AND sessionId='123' AND timeStamp>'9999'"],
                ["DELETE FROM sessions WHERE timeStamp<='9999'"],
                ["SELECT sessionId FROM sessions WHERE userId='1' 
                                 AND sessionId='123' AND timeStamp>'9999'"],
                ["UPDATE sessions SET timeStamp='1219599' WHERE userId='1' AND sessionId='123'"]
            )
            ->willReturnOnConsecutiveCalls(
                $sessionFindResult,
                null,
                $sessionFindResult,
                null
            );

        $this->auth->refreshSession();
    }
}
