<?php

declare(strict_types=1);

namespace BikeShare\Test\Integration\Repository;

use BikeShare\Db\DbInterface;
use BikeShare\Repository\StandRepository;
use BikeShare\Test\Integration\BikeSharingKernelTestCase;

class StandRepositoryTest extends BikeSharingKernelTestCase
{
    public function testFindItemByNamePrefersActiveDuplicateOverInactiveLegacyStand(): void
    {
        $db = self::getContainer()->get(DbInterface::class);
        $db->query('ALTER TABLE stands DROP INDEX standName');

        try {
            $db->query(
                'UPDATE stands SET standName = :duplicateName, status = :status WHERE standId = :standId',
                [
                    'duplicateName' => 'STAND5',
                    'status' => 'inactive',
                    'standId' => 2,
                ]
            );

            $stand = self::getContainer()->get(StandRepository::class)->findItemByName('STAND5');

            $this->assertSame(5, (int)$stand['standId']);
            $this->assertSame('active', $stand['status']);
        } finally {
            $db->query(
                'UPDATE stands SET standName = :standName, status = :status WHERE standId = :standId',
                [
                    'standName' => 'STAND2',
                    'status' => 'active',
                    'standId' => 2,
                ]
            );
            $db->query('ALTER TABLE stands ADD UNIQUE KEY standName (standName)');
        }
    }
}
