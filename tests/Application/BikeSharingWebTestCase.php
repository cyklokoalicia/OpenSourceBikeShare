<?php

declare(strict_types=1);

namespace BikeShare\Test\Application;

use Monolog\Logger;
use Monolog\Level;
use Monolog\LogRecord;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class BikeSharingWebTestCase extends WebTestCase
{
    protected const SERVER_OPTIONS = [];
    protected const CONTAINER_REBOOT_DISABLED = false;

    protected KernelBrowser $client;

    /** @var array<array{level:int, pattern:string|callable}> */
    private array $expected = [];

    /** @var array<string, array{server: bool, env: bool, serverValue: string|null, envValue: string|null}> */
    private array $envBackup = [];

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
     * Override an env var for the current test, with automatic restore in tearDown.
     * Captures whether the var pre-existed in $_SERVER / $_ENV so restoration is faithful.
     */
    protected function setEnvVar(string $name, string $value): void
    {
        if (!array_key_exists($name, $this->envBackup)) {
            $this->envBackup[$name] = [
                'server' => array_key_exists($name, $_SERVER),
                'env' => array_key_exists($name, $_ENV),
                'serverValue' => $_SERVER[$name] ?? null,
                'envValue' => $_ENV[$name] ?? null,
            ];
        }
        $_SERVER[$name] = $value;
        $_ENV[$name] = $value;
    }

    /**
     * Declare that the current test *should* write a log entry.
     * @param string|callable $pattern
     *
     * Examples:
     *  $this->expectLog(Logger::ERROR, '/DB timeout/');
     *  $this->expectLog(Logger::CRITICAL, fn(string $m) => str_contains($m,'payment failed'));
     */
    protected function expectLog(int $level, $pattern): void
    {
        $this->expected[] = ['level' => $level, 'pattern' => $pattern];
    }

    protected function decodeJsonResponse(): array
    {
        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content, 'Response content is not a string');
        self::assertJson($content, 'Response is not valid JSON');

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }

    protected function decodeApiResponseData(): mixed
    {
        $decoded = $this->decodeJsonResponse();
        if (array_key_exists('data', $decoded)) {
            return $decoded['data'];
        }

        return $decoded;
    }

    protected function tearDown(): void
    {
        $logHandler = $this->client->getContainer()->get('monolog.handler.test');
        /**
         * Does a single log record satisfy one expectation?
         */
        $matches = static function (LogRecord $record, array $expected): bool {
            if ($record->level->value !== (int)$expected['level']) {
                return false;
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
            $found = $logHandler->hasRecordThatPasses(
                fn(LogRecord $record) => $matches($record, $expected),
                Level::from((int)$expected['level']),
            );

            self::assertTrue(
                $found,
                sprintf(
                    'Expected %s log matching %s but did not find it.',
                    Level::from((int)$expected['level'])->getName(),
                    \is_callable($expected['pattern']) ? 'closure' : $expected['pattern']
                )
            );
        }

        /*
         * 2) Fail if any **other** WARNING / ERROR / ALERT / CRITICAL was produced.
         */
        $unexpected = [];

        foreach ($logHandler->getRecords() as $record) {
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

        foreach ($this->envBackup as $name => $original) {
            if ($original['server']) {
                $_SERVER[$name] = $original['serverValue'];
            } else {
                unset($_SERVER[$name]);
            }
            if ($original['env']) {
                $_ENV[$name] = $original['envValue'];
            } else {
                unset($_ENV[$name]);
            }
        }
        $this->envBackup = [];

        parent::tearDown();
    }
}
