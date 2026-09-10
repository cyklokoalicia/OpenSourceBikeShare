<?php

declare(strict_types=1);

namespace BikeShare\Test\Integration\Db;

use BikeShare\Db\DbInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class RentalLedgerSchemaTest extends KernelTestCase
{
    private DbInterface $db;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->db = self::getContainer()->get(DbInterface::class);
        $this->db->exec('DROP TABLE IF EXISTS ledger_schema_probe');
        $this->db->exec(
            'CREATE TABLE ledger_schema_probe (
                id INT UNSIGNED PRIMARY KEY,
                bikeNum INT UNSIGNED NOT NULL,
                action VARCHAR(32) NOT NULL,
                pairActionId INT UNSIGNED NULL
            ) ENGINE=InnoDB'
        );
    }

    protected function tearDown(): void
    {
        $this->db->exec('DROP TABLE ledger_schema_probe');
        parent::tearDown();
    }

    public function testIndexPreparationPreservesColumnsAndDoesNotEnforcePairing(): void
    {
        $this->db->exec("INSERT INTO ledger_schema_probe VALUES (1,1,'RENT',NULL),
            (2,1,'RETURN',1), (3,1,'RETURN',1)");
        $sql = file_get_contents(dirname(__DIR__, 3) . '/migrations/0013/01-indexes.sql');
        self::assertIsString($sql);
        $this->db->exec(str_replace('`history`', '`ledger_schema_probe`', $sql));
        self::assertSame([null, 1, 1], array_column($this->db->query(
            'SELECT pairActionId FROM ledger_schema_probe ORDER BY id'
        )->fetchAllAssoc(), 'pairActionId'));
        self::assertSame(['id', 'bikeNum', 'action', 'pairActionId'], array_column(
            $this->db->query('SHOW COLUMNS FROM ledger_schema_probe')->fetchAllAssoc(),
            'Field',
        ));
        // Deliberately invalid domain data: index preparation must not add hidden pairing rules.
        $this->db->exec("INSERT INTO ledger_schema_probe VALUES (4,1,'RENT',999)");
        $this->db->exec('DELETE FROM ledger_schema_probe WHERE id=1');
        self::assertSame(3, (int)$this->db->query('SELECT COUNT(*) AS n FROM ledger_schema_probe')->fetchAssoc()['n']);
    }

    public function testBootstrapUsesOnlyOrdinaryPairIndexes(): void
    {
        $indexes = $this->db->query("SHOW INDEX FROM history WHERE Column_name='pairActionId'")->fetchAllAssoc();
        self::assertNotEmpty($indexes);
        foreach ($indexes as $index) {
            self::assertSame(1, (int)$index['Non_unique']);
        }
        $constraints = $this->db->query(
            "SELECT CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='history'
               AND CONSTRAINT_TYPE IN ('FOREIGN KEY','CHECK')"
        )->fetchAllAssoc();
        self::assertSame([], $constraints);
    }
}
