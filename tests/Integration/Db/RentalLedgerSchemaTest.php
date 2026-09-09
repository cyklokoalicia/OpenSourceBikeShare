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

    public function testExpandPreservesDirtyLegacyPairsAndEnforcementRejectsThem(): void
    {
        $this->db->exec("INSERT INTO ledger_schema_probe VALUES (1,1,'RENT',NULL),
            (2,1,'RETURN',1), (3,1,'RETURN',1)");
        $this->apply('01-expand.sql');
        $rows = $this->db->query(
            'SELECT pairActionId, ledgerVersion FROM ledger_schema_probe ORDER BY id'
        )->fetchAllAssoc();
        self::assertSame([null, 1, 1], array_column($rows, 'pairActionId'));
        self::assertSame([null, null, null], array_column($rows, 'ledgerVersion'));
        $this->expectException(\PDOException::class);
        $this->apply('02-enforce.sql');
    }

    public function testNormalizedPairCannotBeClosedTwiceEvenByRevert(): void
    {
        $this->prepareVerifiedPair();
        $this->expectException(\PDOException::class);
        $this->db->exec("INSERT INTO ledger_schema_probe VALUES
            (3,1,'REVERT',1,1,'command','rental','cancelled')");
    }

    public function testVerifiedReturnRequiresPairAndMetadata(): void
    {
        $this->prepareVerifiedPair();
        $this->expectException(\PDOException::class);
        $this->db->exec("INSERT INTO ledger_schema_probe VALUES
            (3,1,'RETURN',NULL,1,NULL,'rental','returned')");
    }

    public function testPairMustReferenceAnExistingRow(): void
    {
        $this->prepareVerifiedPair();
        $this->expectException(\PDOException::class);
        $this->db->exec("INSERT INTO ledger_schema_probe VALUES
            (3,1,'RETURN',999,1,'command','rental','returned')");
    }

    private function prepareVerifiedPair(): void
    {
        $this->apply('01-expand.sql');
        $this->apply('02-enforce.sql');
        $this->db->exec("INSERT INTO ledger_schema_probe VALUES
            (1,1,'RENT',NULL,1,'command','rental',NULL),
            (2,1,'RETURN',1,1,'command','rental','returned')");
    }

    private function apply(string $name): void
    {
        $sql = file_get_contents(dirname(__DIR__, 3) . '/migrations/0013/' . $name);
        self::assertIsString($sql);
        $sql = str_replace(['`history`', 'fk_history_pair', 'chk_history_ledger'], [
            '`ledger_schema_probe`', 'fk_probe_pair', 'chk_probe_ledger',
        ], $sql);
        $this->db->exec($sql);
    }
}
