<?php

declare(strict_types=1);

namespace BikeShare\Test\Integration\Repository;

use BikeShare\Db\DbInterface;
use BikeShare\Repository\UserRepository;
use BikeShare\Test\Integration\BikeSharingKernelTestCase;

class UserRepositoryFindAdminsTest extends BikeSharingKernelTestCase
{
    /** Fixture admins: privileges 7. */
    private const ADMIN_USER_IDS = [7, 12];
    /** Fixture user with privileges 1: privileged, but not an admin. */
    private const PRIVILEGED_USER_ID = 5;

    protected function tearDown(): void
    {
        $this->setPrivileges(self::PRIVILEGED_USER_ID, 1);
        parent::tearDown();
    }

    public function testReturnsOnlyAdminsWithTheirContacts(): void
    {
        $admins = self::getContainer()->get(UserRepository::class)->findAdmins();

        $this->assertSame(self::ADMIN_USER_IDS, array_map('intval', array_column($admins, 'userId')));
        $this->assertSame(['userId', 'number', 'mail'], array_keys($admins[0]));
        $this->assertSame('421951777777', $admins[0]['number']);
    }

    public function testAdminBitMakesAUserAnAdminAndOrderIsByUserId(): void
    {
        $this->setPrivileges(self::PRIVILEGED_USER_ID, 3);

        $admins = self::getContainer()->get(UserRepository::class)->findAdmins();

        $this->assertSame(
            [self::PRIVILEGED_USER_ID, ...self::ADMIN_USER_IDS],
            array_map('intval', array_column($admins, 'userId'))
        );
    }

    private function setPrivileges(int $userId, int $privileges): void
    {
        self::getContainer()->get(DbInterface::class)->query(
            'UPDATE users SET privileges = :privileges WHERE userId = :userId',
            ['privileges' => $privileges, 'userId' => $userId]
        );
    }
}
