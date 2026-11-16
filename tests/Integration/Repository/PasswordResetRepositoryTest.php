<?php

declare(strict_types=1);

namespace BikeShare\Test\Integration\Repository;

use BikeShare\Db\DbInterface;
use BikeShare\Repository\PasswordResetRepository;
use BikeShare\Test\Integration\BikeSharingKernelTestCase;

class PasswordResetRepositoryTest extends BikeSharingKernelTestCase
{
    private const USER_ID = 4;

    protected function tearDown(): void
    {
        self::getContainer()->get(PasswordResetRepository::class)->deleteByUserId(self::USER_ID);
        parent::tearDown();
    }

    public function testATokenIsConsumedOnlyOnce(): void
    {
        $repository = self::getContainer()->get(PasswordResetRepository::class);
        $tokenHash = hash('sha256', 'token');
        $repository->save(self::USER_ID, $tokenHash, 60);

        $this->assertFalse($repository->consume(self::USER_ID, hash('sha256', 'other')), 'Another token');
        $this->assertTrue($repository->consume(self::USER_ID, $tokenHash));
        $this->assertFalse($repository->consume(self::USER_ID, $tokenHash), 'The second use');
        $this->assertNull($repository->findUserIdByTokenHash($tokenHash));
    }

    public function testAnExpiredTokenCannotBeConsumed(): void
    {
        $repository = self::getContainer()->get(PasswordResetRepository::class);
        $tokenHash = hash('sha256', 'token');
        $repository->save(self::USER_ID, $tokenHash, 60);
        self::getContainer()->get(DbInterface::class)->query(
            'UPDATE passwordReset SET expiresAt = :past WHERE userId = :userId',
            ['past' => '2000-01-01 00:00:00', 'userId' => self::USER_ID]
        );

        $this->assertFalse($repository->consume(self::USER_ID, $tokenHash));
    }
}
