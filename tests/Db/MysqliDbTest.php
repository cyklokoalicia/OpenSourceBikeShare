<?php

namespace Test\BikeShare\Db;

use BikeShare\Db\DbResultInterface;
use BikeShare\Db\MysqliDb;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MysqliDbTest extends TestCase
{
    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;

    public function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $db = new MysqliDb('server', 'user', 'password', 'dbname', $this->logger, true);
        $mysqliMock = $this->createMock(\mysqli::class);

        $reflection = new \ReflectionClass($db);
        $reflection_property = $reflection->getProperty('conn');
        $reflection_property->setAccessible(true);
        $reflection_property->setValue($db, $mysqliMock);

        $this->db = $db;
        $this->conn = $mysqliMock;
    }

    public function testQuery()
    {
        $query = 'SELECT * FROM table';
        $result = $this->createMock(\mysqli_result::class);
        $this->conn->expects($this->once())
            ->method('query')
            ->with($query)
            ->willReturn($result);

        $this->assertTrue(is_a($this->db->query($query), DbResultInterface::class));
    }
    public function testQueryError()
    {
        $query = 'SELECT * FROM table';
        $result = $this->createMock(\mysqli_result::class);
        $this->conn->expects($this->once())
            ->method('query')
            ->with($query)
            ->willReturn(false);
        $this->conn->expects($this->once())
            ->method('rollback');

        $this->logger->expects($this->once())
            ->method('error')
            ->with('DB query error', $this->callback(function () {
                return true;
            }));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB error in : ' . $query);

        $this->assertSame($result, $this->db->query($query));
    }

    public function testEscape()
    {
        $string = "string";
        $escapedString = "escapedString";
        $this->conn->expects($this->once())
            ->method('real_escape_string')
            ->with($string)
            ->willReturn($escapedString);

        $this->assertSame($escapedString, $this->db->escape($string));
    }
    public function testSetAutocommit()
    {
        $this->conn->expects($this->once())
            ->method('autocommit')
            ->with(false);

        $this->db->setAutocommit(false);
    }

    public function testCommit()
    {
        $this->conn->expects($this->once())
            ->method('commit');

        $this->db->commit();
    }

    public function testRollback()
    {
        $this->conn->expects($this->once())
            ->method('rollback');

        $this->db->rollback();
    }
}
