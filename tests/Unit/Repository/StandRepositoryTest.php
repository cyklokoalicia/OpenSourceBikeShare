<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\Repository;

use BikeShare\Db\DbInterface;
use BikeShare\Db\DbResultInterface;
use BikeShare\Repository\CityRepository;
use BikeShare\Repository\StandRepository;
use PHPUnit\Framework\TestCase;

class StandRepositoryTest extends TestCase
{
    public function testFindItemByNamePrefersActiveLegacyDuplicateBeforeInactive(): void
    {
        $db = $this->createMock(DbInterface::class);
        $result = $this->createMock(DbResultInterface::class);

        $db->expects($this->once())
            ->method('query')
            ->with(
                $this->callback(
                    static fn(string $query): bool => str_contains($query, 'ORDER BY CASE status')
                        && str_contains($query, 'WHEN :statusActive THEN 0')
                        && str_contains($query, 'WHEN :statusInactive THEN 4')
                        && str_contains($query, 'standId DESC')
                ),
                [
                    'standName' => 'REDUTA',
                    'statusActive' => 'active',
                    'statusTechnical' => 'technical',
                    'statusHidden' => 'hidden',
                    'statusVirtual' => 'virtual',
                    'statusInactive' => 'inactive',
                ]
            )
            ->willReturn($result);

        $result->expects($this->once())
            ->method('fetchAssoc')
            ->willReturn([
                'standId' => 57,
                'standName' => 'REDUTA',
                'status' => 'active',
            ]);

        $repository = new StandRepository($db, new CityRepository(['Bratislava' => 'Bratislava']));

        $stand = $repository->findItemByName('REDUTA');

        $this->assertSame(57, $stand['standId']);
        $this->assertSame('active', $stand['status']);
    }
}
