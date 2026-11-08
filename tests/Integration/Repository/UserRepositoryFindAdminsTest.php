<?php

declare(strict_types=1);

namespace BikeShare\Test\Integration\Repository;

use BikeShare\Db\DbInterface;
use BikeShare\Repository\UserRepository;
use BikeShare\Test\Integration\BikeSharingKernelTestCase;

class UserRepositoryFindAdminsTest extends BikeSharingKernelTestCase
{
    public function testReturnsOnlyPrivilegedAdmins(): void
    {
        $db = self::getContainer()->get(DbInterface::class);
        $expected = $db
            ->query('SELECT userId, number, mail FROM users WHERE privileges & 2 != 0')
            ->fetchAllAssoc();

        $actual = self::getContainer()->get(UserRepository::class)->findAdmins();

        $this->assertNotEmpty($actual);
        $normalize = static function (array $rows): array {
            $rows = array_map(
                static fn(array $row): array => [
                    'userId' => (int) $row['userId'],
                    'number' => (string) $row['number'],
                    'mail' => (string) $row['mail'],
                ],
                $rows
            );
            usort($rows, static fn(array $a, array $b): int => $a['userId'] <=> $b['userId']);

            return $rows;
        };

        $this->assertSame($normalize($expected), $normalize($actual));
    }
}
