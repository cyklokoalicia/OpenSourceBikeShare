<?php

declare(strict_types=1);

namespace BikeShare\Test\Integration\Repository;

use BikeShare\Repository\PasswordResetRepository;
use BikeShare\Test\Integration\BikeSharingKernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

class PasswordResetRepositoryTest extends BikeSharingKernelTestCase
{
    use ClockSensitiveTrait;

    private const USER_ID = 4;

    protected function tearDown(): void
    {
        self::getContainer()->get(PasswordResetRepository::class)->deleteByUserId(self::USER_ID);
        parent::tearDown();
    }

    public function testATokenIsConsumedOnlyOnce(): void
    {
        $repository = self::getContainer()->get(PasswordResetRepository::class);
        $repository->save(self::USER_ID, 'token', 60);

        $this->assertFalse($repository->consume('other'), 'Another token');
        $this->assertTrue($repository->consume('token'));
        $this->assertFalse($repository->consume('token'), 'The second use');
        $this->assertNull($repository->findUserIdByToken('token'));
    }

    public function testAnExpiredTokenCannotBeConsumed(): void
    {
        $repository = self::getContainer()->get(PasswordResetRepository::class);
        $repository->save(self::USER_ID, 'token', 60);
        static::mockTime('+61 minutes');

        $this->assertFalse($repository->consume('token'));
    }
}
