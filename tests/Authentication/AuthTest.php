<?php

namespace Test\BikeShare\Authentication;

use BikeShare\Authentication\Auth;
use BikeShare\Db\DbInterface;
use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase
{
    /**
     * @phpcs:disable PSR12.Properties.ConstantVisibility
     */
    const SESSION_EXPIRATION = 86400 * 14;
    /**
     * @var DbInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $db;
    /**
     * @var Auth
     */
    private $auth;

    public function setUp()
    {
        $this->db = $this->createMock(DbInterface::class);
        $this->auth = new Auth(
            $this->db
        );
    }

    protected function tearDown()
    {
        unset(
            $this->db,
            $this->auth
        );
    }


    /**
     * @dataProvider testGetUserIdDataProvider
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

    public function testGetUserIdDataProvider()
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
     * @dataProvider testGetSessionIdDataProvider
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

    public function testGetSessionIdDataProvider()
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
        $this->db->expects($this->exactly(2))
            ->method('escape')
            ->withConsecutive(
                [$number],
                [$password]
            )->willReturnOnConsecutiveCalls($number, $password);

        $sessionId = hash('sha256', $userId . $number . '9999');

        $this->db->expects($this->exactly(3))
            ->method('query')
            ->withConsecutive(
                ["SELECT userId FROM users WHERE number='$number' AND password=SHA2('$password',512)"],
                ["DELETE FROM sessions WHERE userId='{$userId}'"],
                ["INSERT INTO sessions SET userId='{$userId}',sessionId='{$sessionId}',timeStamp='1219599'"]
            )
            ->willReturnOnConsecutiveCalls(
                new \Test\BikeShare\MysqliResult(1, [['userId' => '123']]),
                null,
                null
            );


        $this->auth->login($number, $password);
    }

    /**
     * @dataProvider testisLoggedInDataProvider
     */
    public function testisLoggedIn(
        $userId = 0,
        $sessionId = '',
        $escapeCallParams = [],
        $escapeCallResults = [],
        $expectedResult = false
    ) {
        if ($userId) {
            $_COOKIE["loguserid"] = $userId;
        }
        if ($sessionId) {
            $_COOKIE["logsession"] = $sessionId;
        }
        $this->db->expects($this->exactly(count($escapeCallParams)))
            ->method('escape')
            ->withConsecutive(...$escapeCallParams)
            ->willReturnOnConsecutiveCalls(...$escapeCallResults);

        $this->db->expects(count($escapeCallParams) > 0 ? $this->exactly(1) : $this->never())
            ->method('query')
            ->withConsecutive(
                ["SELECT sessionId FROM sessions WHERE
                                   userId='$userId' AND sessionId='$sessionId' AND timeStamp>'9999'"]
            )
            ->willReturnOnConsecutiveCalls(
                new \Test\BikeShare\MysqliResult(1, [['sessionId' => '123']])
            );

        $this->assertEquals($expectedResult, $this->auth->isLoggedIn());
    }

    public function testisLoggedInDataProvider()
    {
        yield 'no user id' => [
            'userId' => 0,
            'sessionId' => '',
            'escapeCallParams' => [],
            'escapeCallResults' => [],
            'expectedResult' => false,
        ];
        yield 'no session id' => [
            'userId' => 1,
            'sessionId' => '',
            'escapeCallParams' => [],
            'escapeCallResults' => [],
            'expectedResult' => false,
        ];
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

        $this->db->expects($this->exactly(2))
            ->method('query')
            ->withConsecutive(
                ["SELECT sessionId FROM sessions WHERE
                                   userId='1' AND sessionId='123' AND timeStamp>'9999'"],
                ["DELETE FROM sessions WHERE userId='$userId' OR sessionId='$sessionId'"]
            )
            ->willReturnOnConsecutiveCalls(
                new \Test\BikeShare\MysqliResult(1, [['sessionId' => '123']]),
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
                new \Test\BikeShare\MysqliResult(1, [['sessionId' => '123']]),
                null,
                new \Test\BikeShare\MysqliResult(1, [['sessionId' => '123']]),
                null
            );

        $this->auth->refreshSession();
    }
}

/**
 * @phpcs:disable PSR1.Files.SideEffects
 */
namespace BikeShare\Authentication;
{
function header($header, $replace = true, $response_code = 0)
{
}

function setcookie($name, $value = '', $options = 0)
{
    return true;
}

function time()
{
    return 9999;
}
}
