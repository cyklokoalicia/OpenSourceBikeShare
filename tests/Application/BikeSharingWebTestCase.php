<?php

declare(strict_types=1);

namespace BikeShare\Test\Application;

use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class BikeSharingWebTestCase extends WebTestCase
{
    protected const SERVER_OPTIONS = [];
    protected const CONTAINER_REBOOT_DISABLED = false;

    protected KernelBrowser $client;

    /** @var array<array{level:int, pattern:string|callable}> */
    private array $expected = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient([], static::SERVER_OPTIONS);

        if (static::CONTAINER_REBOOT_DISABLED) {
            $this->client->disableReboot();
        }

        // grab & reset the monolog TestHandler
        $handler = $this->client->getContainer()->get('monolog.handler.test');
        $handler->clear();
        $this->expected = [];
    }

    /**
     * Declare that the current test *should* write a log entry.
     * @var int $level
     * @var string|callable $pattern
     *
     * Examples:
     *  $this->expectLog(Logger::ERROR, '/DB timeout/');
     *  $this->expectLog(Logger::CRITICAL, fn(string $m) => str_contains($m,'payment failed'));
     */
    protected function expectLog(int $level, $pattern): void
    {
        $this->expected[] = ['level' => $level, 'pattern' => $pattern];
    }

    protected function tearDown(): void
    {
        $logHandler = $this->client->getContainer()->get('monolog.handler.test');
        /**
         * Does a single log record satisfy one expectation?
         */
        $matches = static function (array $record, array $expected): bool {
            if ($record['level'] !== $expected['level']) {
                return false;
            }

            $message = $record['message'];

            return \is_callable($expected['pattern'])
                ? ($expected['pattern'])($message)
                : (\preg_match($expected['pattern'], $message) === 1);
        };

        /*
         * 1) Verify that every declared expectation actually happened.
         */
        foreach ($this->expected as $expected) {
            $found = $logHandler->hasRecordThatPasses(
                fn(array $record) => $matches($record, $expected),
                $expected['level'],
            );

            self::assertTrue(
                $found,
                sprintf(
                    'Expected %s log matching %s but did not find it.',
                    Logger::getLevelName($expected['level']),
                    \is_callable($expected['pattern']) ? 'closure' : $expected['pattern']
                )
            );
        }

        /*
         * 2) Fail if any **other** WARNING / ERROR / ALERT / CRITICAL was produced.
         */
        $unexpected = [];

        foreach ($logHandler->getRecords() as $record) {
            if ($record['level'] < Logger::WARNING) {
                // only care about ERROR and above
                continue;
            }

            $isExpected = false;
            foreach ($this->expected as $expected) {
                if ($matches($record, $expected)) {
                    $isExpected = true;
                    break;
                }
            }

            if (!$isExpected) {
                $unexpected[] = $record;
            }
        }

        self::assertSame(
            [],
            $unexpected,
            'Unexpected high-severity log(s): ' . \json_encode($unexpected, JSON_PRETTY_PRINT)
        );

        /*
         * 3) Clean up for re-use / multiple tearDown() calls.
         */
        $this->expected = [];

        parent::tearDown();
    }
}
