<?php

declare(strict_types=1);

namespace BikeShare\Test\Integration;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\Level;
use Monolog\LogRecord;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class BikeSharingKernelTestCase extends WebTestCase
{
    protected TestHandler $logHandler;

    /** @var array<array{level:int, pattern:string|callable, context:array<string, mixed>}> */
    private array $expected = [];

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        // grab & reset the monolog TestHandler
        $handler = self::getContainer()->get('monolog.handler.test');
        $this->logHandler = $handler;
        $handler->clear();
        $this->expected = [];
    }

    /**
     * Declare that the current test *should* write a log entry.
     * @param string|callable $pattern
     *
     * Examples:
     *  $this->expectLog(Logger::ERROR, '/DB timeout/');
     *  $this->expectLog(Logger::CRITICAL, fn(string $m) => str_contains($m,'payment failed'));
     *  $this->expectLog(Logger::ERROR, '/Notification delivery failed/', ['type' => 'password-reset', 'userId' => 4]);
     *
     * @param array<string, mixed> $context the record's context must contain every given key with an equal value
     *                                      (subset match, loose `==` so ints match numeric strings)
     */
    protected function expectLog(int $level, $pattern, array $context = []): void
    {
        $this->expected[] = ['level' => $level, 'pattern' => $pattern, 'context' => $context];
    }

    protected function tearDown(): void
    {
        /**
         * Does a single log record satisfy one expectation?
         */
        $matches = static function (LogRecord $record, array $expected): bool {
            if ($record->level->value !== (int)$expected['level']) {
                return false;
            }

            foreach ($expected['context'] ?? [] as $key => $value) {
                if (!\array_key_exists($key, $record->context) || $record->context[$key] != $value) {
                    return false;
                }
            }

            $message = $record->message;

            return \is_callable($expected['pattern'])
                ? ($expected['pattern'])($message)
                : (\preg_match($expected['pattern'], $message) === 1);
        };

        /*
         * 1) Verify that every declared expectation actually happened.
         */
        foreach ($this->expected as $expected) {
            $found = $this->logHandler->hasRecordThatPasses(
                fn(LogRecord $record) => $matches($record, $expected),
                Level::from((int)$expected['level']),
            );

            self::assertTrue(
                $found,
                sprintf(
                    'Expected %s log matching %s%s but did not find it.',
                    Logger::getLevelName((int)$expected['level']),
                    \is_callable($expected['pattern']) ? 'closure' : $expected['pattern'],
                    $expected['context'] === [] ? '' : ' with context ' . \json_encode($expected['context'])
                )
            );
        }

        /*
         * 2) Fail if any **other** WARNING / ERROR / ALERT / CRITICAL was produced.
         */
        $unexpected = [];

        foreach ($this->logHandler->getRecords() as $record) {
            if ($record->level->value < Logger::WARNING) {
                // only care about ERROR and above
                continue;
            }
            $isExpected = array_any($this->expected, fn($expected) => $matches($record, $expected));

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
