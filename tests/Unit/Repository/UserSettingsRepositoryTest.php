<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\Repository;

use BikeShare\Db\DbInterface;
use BikeShare\Db\DbResultInterface;
use BikeShare\Repository\UserSettingsRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class UserSettingsRepositoryTest extends TestCase
{
    private const array DEFAULTS = ['locale' => 'sk', 'allowGeoDetection' => false, 'showWelcomePage' => false];

    #[DataProvider('storedSettingsProvider')]
    public function testFindByUserIdAlwaysReturnsAFullTypedSet(?string $storedJson, array $expected): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $this->assertSame($expected, $this->repository($storedJson, $logger)->findByUserId(7));
    }

    public static function storedSettingsProvider(): iterable
    {
        yield 'no row' => [null, self::DEFAULTS];
        yield 'empty object' => ['{}', self::DEFAULTS];
        yield 'partial' => ['{"locale":"uk"}', array_merge(self::DEFAULTS, ['locale' => 'uk'])];
        yield 'all set' => [
            '{"locale":"de","allowGeoDetection":true,"showWelcomePage":true}',
            ['locale' => 'de', 'allowGeoDetection' => true, 'showWelcomePage' => true],
        ];
        yield 'wrong types fall back per key' => [
            '{"locale":null,"allowGeoDetection":"yes","showWelcomePage":true}',
            array_merge(self::DEFAULTS, ['showWelcomePage' => true]),
        ];
        yield 'empty locale' => ['{"locale":""}', self::DEFAULTS];
        yield 'not an object' => ['5', self::DEFAULTS];
        yield 'unknown keys are dropped' => ['{"foo":"bar"}', self::DEFAULTS];
    }

    public function testUnparsableJsonIsLoggedAndGivesDefaults(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with('Error parsing user settings');

        $this->assertSame(self::DEFAULTS, $this->repository('{not json', $logger)->findByUserId(7));
    }

    private function repository(?string $storedJson, LoggerInterface $logger): UserSettingsRepository
    {
        $result = $this->createStub(DbResultInterface::class);
        $result->method('fetchAssoc')->willReturn(
            $storedJson === null ? null : ['userId' => 7, 'settings' => $storedJson]
        );
        $db = $this->createStub(DbInterface::class);
        $db->method('query')->willReturn($result);

        return new UserSettingsRepository($db, 'sk', $logger);
    }
}
