<?php

declare(strict_types=1);

namespace BikeShare\Test\Integration\Repository;

use BikeShare\Db\DbInterface;
use BikeShare\Repository\SentRepository;
use BikeShare\Test\Integration\BikeSharingKernelTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class SentRepositoryTest extends BikeSharingKernelTestCase
{
    private const NUMBER = '421900000123';

    protected function tearDown(): void
    {
        self::getContainer()->get(DbInterface::class)
            ->query('DELETE FROM sent WHERE number = :number', ['number' => self::NUMBER]);
        parent::tearDown();
    }

    #[DataProvider('textProvider')]
    public function testStoresTextUpToTheColumnLength(string $text, string $expected): void
    {
        self::getContainer()->get(SentRepository::class)->add(self::NUMBER, $text);

        $rows = self::getContainer()->get(DbInterface::class)
            ->query('SELECT text FROM sent WHERE number = :number', ['number' => self::NUMBER])
            ->fetchAllAssoc();
        $this->assertSame([$expected], array_column($rows, 'text'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function textProvider(): iterable
    {
        yield 'short text as is' => ['Bike 1 is at stand A.', 'Bike 1 is at stand A.'];
        yield 'exactly the limit as is' => [str_repeat('a', 2000), str_repeat('a', 2000)];
        yield 'longer text cut with dots' => [str_repeat('a', 2001), str_repeat('a', 1997) . '...'];
        yield 'multibyte counted as characters' => [str_repeat('č', 2500), str_repeat('č', 1997) . '...'];
    }
}
