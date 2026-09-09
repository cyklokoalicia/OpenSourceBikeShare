<?php

declare(strict_types=1);

namespace BikeShare\Test\Integration\Db;

use BikeShare\Db\DbInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PdoDbTransactionTest extends KernelTestCase
{
    private DbInterface $db;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->db = self::getContainer()->get(DbInterface::class);
        $this->db->exec('CREATE TEMPORARY TABLE transaction_probe (id INT PRIMARY KEY) ENGINE=InnoDB');
    }

    protected function tearDown(): void
    {
        $this->db->exec('DROP TEMPORARY TABLE transaction_probe');
        parent::tearDown();
    }

    public function testCommitsAndReturnsTheCallbackResult(): void
    {
        $result = $this->db->transactional(function (): int {
            self::assertTrue($this->db->isTransactionActive());
            $this->db->query('INSERT INTO transaction_probe VALUES (1)');

            return 42;
        });

        self::assertSame(42, $result);
        self::assertFalse($this->db->isTransactionActive());
        self::assertSame(1, $this->countRows());
    }

    public function testErrorRollsBackAndConnectionCanBeReused(): void
    {
        $failure = new \Error('failure after write');
        try {
            $this->db->transactional(function () use ($failure): void {
                $this->db->query('INSERT INTO transaction_probe VALUES (1)');

                throw $failure;
            });
        } catch (\Error $caught) {
            self::assertSame($failure, $caught);
        }
        self::assertSame(0, $this->countRows());
        self::assertFalse($this->db->isTransactionActive());
        $this->db->transactional(fn() => $this->db->query('INSERT INTO transaction_probe VALUES (2)'));
        self::assertSame(1, $this->countRows());
    }

    public function testNestedTransactionIsRejectedAndOuterWritesRollBack(): void
    {
        try {
            $this->db->transactional(function (): void {
                $this->db->query('INSERT INTO transaction_probe VALUES (1)');
                $this->db->transactional(function (): void {
                    self::fail('A nested callback must never run');
                });
            });
        } catch (\LogicException $exception) {
            self::assertSame('Nested transactions are not supported.', $exception->getMessage());
        }
        self::assertFalse($this->db->isTransactionActive());
        self::assertSame(0, $this->countRows());
    }

    private function countRows(): int
    {
        return (int)$this->db->query('SELECT COUNT(*) AS n FROM transaction_probe')->fetchAssoc()['n'];
    }
}
