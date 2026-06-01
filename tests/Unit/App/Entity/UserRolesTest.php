<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\App\Entity;

use BikeShare\App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserRolesTest extends TestCase
{
    private function makeUser(int $privileges, bool $confirmed, bool $smsEnabled): User
    {
        return new User(
            1,
            '421900000000',
            'mail@example.com',
            'hash',
            'City',
            'Name',
            $privileges,
            // effective confirmation — UserProvider folds the SMS-enabled config in
            !$smsEnabled || $confirmed,
            new \DateTimeImmutable('2026-01-01'),
        );
    }

    public function testUnconfirmedUserWithSmsEnabledIsNewbieOnly(): void
    {
        self::assertSame(['ROLE_NEWBIE'], $this->makeUser(0, false, true)->getRoles());
    }

    public function testUnconfirmedAdminWithSmsEnabledIsNewbieOnly(): void
    {
        self::assertSame(['ROLE_NEWBIE'], $this->makeUser(1, false, true)->getRoles());
    }

    public function testConfirmedUserHasRoleUser(): void
    {
        self::assertSame(['ROLE_USER'], $this->makeUser(0, true, true)->getRoles());
    }

    public function testConfirmedAdminHasAdminRoles(): void
    {
        self::assertSame(['ROLE_USER', 'ROLE_ADMIN'], $this->makeUser(1, true, true)->getRoles());
    }

    public function testConfirmedSuperAdminHasAllRoles(): void
    {
        self::assertSame(
            ['ROLE_USER', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
            $this->makeUser(7, true, true)->getRoles()
        );
    }

    public function testUnconfirmedUserWithSmsDisabledIsRoleUser(): void
    {
        self::assertSame(['ROLE_USER'], $this->makeUser(0, false, false)->getRoles());
    }
}
