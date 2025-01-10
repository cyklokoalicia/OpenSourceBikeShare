<?php

declare(strict_types=1);

namespace Test\BikeShare\SmsCommand;

use BikeShare\SmsCommand\CommandDetector;
use PHPUnit\Framework\TestCase;

class CommandDetectorTest extends TestCase
{
    /**
     * @dataProvider detectDataProvider
     */
    public function testDetect(string $command, array $expected)
    {
        $detector = new CommandDetector();
        $this->assertEquals($expected, $detector->detect($command));
    }

    /**
     * @phpcs:disable Generic.Files.LineLength
     */
    public function detectDataProvider(): iterable
    {
        yield 'HELP' => [
            'command' => 'HELP',
            'expected' => ['command' => 'HELP', 'arguments' => []],
        ];
        yield 'CREDIT' => [
            'command' => 'CREDIT',
            'expected' => ['command' => 'CREDIT', 'arguments' => []],
        ];
        yield 'FREE' => [
            'command' => 'FREE',
            'expected' => ['command' => 'FREE', 'arguments' => []],
        ];
        yield 'RENT' => [
            'command' => 'RENT 42',
            'expected' => ['command' => 'RENT', 'arguments' => ['bikeNumber' => '42']],
        ];
        yield 'RETURN' => [
            'command' => 'RETURN 42 MAINSQUEARE',
            'expected' => ['command' => 'RETURN', 'arguments' => ['bikeNumber' => '42', 'standName' => 'MAINSQUEARE', 'note' => null]],
        ];
        yield 'RETURN with note' => [
            'command' => 'RETURN 42 MAINSQUEARE broken',
            'expected' => ['command' => 'RETURN', 'arguments' => ['bikeNumber' => '42', 'standName' => 'MAINSQUEARE', 'note' => 'broken']],
        ];
        yield 'WHERE' => [
            'command' => 'WHERE 42',
            'expected' => ['command' => 'WHERE', 'arguments' => ['bikeNumber' => '42']],
        ];
        yield 'INFO' => [
            'command' => 'INFO MAINSQUEARE',
            'expected' => ['command' => 'INFO', 'arguments' => ['standName' => 'MAINSQUEARE']],
        ];
        yield 'NOTE bike' => [
            'command' => 'NOTE 42 broken',
            'expected' => ['command' => 'NOTE', 'arguments' => ['bikeNumber' => '42', 'note' => 'broken']],
        ];
        yield 'NOTE standName' => [
            'command' => 'NOTE MAINSQUEARE broken',
            'expected' => ['command' => 'NOTE', 'arguments' => ['standName' => 'MAINSQUEARE', 'note' => 'broken']],
        ];
        yield 'FORCERENT' => [
            'command' => 'FORCERENT 42',
            'expected' => ['command' => 'FORCERENT', 'arguments' => ['bikeNumber' => '42']],
        ];
        yield 'FORCERETURN' => [
            'command' => 'FORCERETURN 42 MAINSQUEARE',
            'expected' => ['command' => 'FORCERETURN', 'arguments' => ['bikeNumber' => '42', 'standName' => 'MAINSQUEARE', 'note' => null]],
        ];
        yield 'FORCERETURN with note' => [
            'command' => 'FORCERETURN 42 MAINSQUEARE broken',
            'expected' => ['command' => 'FORCERETURN', 'arguments' => ['bikeNumber' => '42', 'standName' => 'MAINSQUEARE', 'note' => 'broken']],
        ];
        yield 'LIST' => [
            'command' => 'LIST MAINSQUEARE',
            'expected' => ['command' => 'LIST', 'arguments' => ['standName' => 'MAINSQUEARE']],
        ];
        yield 'LAST' => [
            'command' => 'LAST 42',
            'expected' => ['command' => 'LAST', 'arguments' => ['bikeNumber' => '42']],
        ];
        yield 'REVERT' => [
            'command' => 'REVERT 42',
            'expected' => ['command' => 'REVERT', 'arguments' => ['bikeNumber' => '42']],
        ];
        yield 'ADD' => [
            'command' => 'ADD king@earth.com 0901456789 Martin Luther King Jr.',
            'expected' => ['command' => 'ADD', 'arguments' => ['email' => 'king@earth.com', 'phone' => '0901456789', 'fullName' => 'Martin Luther King Jr.']],
        ];
        yield 'ADD with many spaces' => [
            'command' => 'ADD    king@earth.com     0901456789 Martin     Luther King Jr.',
            'expected' => ['command' => 'ADD', 'arguments' => ['email' => 'king@earth.com', 'phone' => '0901456789', 'fullName' => 'Martin     Luther King Jr.']],
        ];
        yield 'DELNOTE all' => [
            'command' => 'DELNOTE 42',
            'expected' => ['command' => 'DELNOTE', 'arguments' => ['bikeNumber' => '42', 'pattern' => null]],
        ];
        yield 'DELNOTE pattern' => [
            'command' => 'DELNOTE 42 wheel',
            'expected' => ['command' => 'DELNOTE', 'arguments' => ['bikeNumber' => '42', 'pattern' => 'wheel']],
        ];
        yield 'TAG' => [
            'command' => 'TAG MAINSQUEARE broken',
            'expected' => ['command' => 'TAG', 'arguments' => ['standName' => 'MAINSQUEARE', 'note' => 'broken']],
        ];
        yield 'UNTAG all' => [
            'command' => 'UNTAG MAINSQUEARE',
            'expected' => ['command' => 'UNTAG', 'arguments' => ['standName' => 'MAINSQUEARE', 'pattern' => null]],
        ];
        yield 'UNTAG pattern' => [
            'command' => 'UNTAG MAINSQUEARE broken',
            'expected' => ['command' => 'UNTAG', 'arguments' => ['standName' => 'MAINSQUEARE', 'pattern' => 'broken']],
        ];
        yield 'invalid command' => [
            'command' => 'INVALID',
            'expected' => ['command' => 'UNKNOWN', 'possibleCommand' => 'INVALID', 'arguments' => []],
        ];
        yield 'invalid command2' => [
            'command' => 'HELP 123',
            'expected' => ['command' => 'HELP', 'arguments' => []],
        ];
        yield 'invalid command3' => [
            'command' => 'RENT MAINSQUEARE 42',
            'expected' => ['command' => 'UNKNOWN', 'possibleCommand' => 'RENT' ,'arguments' => []],
        ];
        yield 'rent' => [
            'command' => 'rent 42',
            'expected' => ['command' => 'RENT', 'arguments' => ['bikeNumber' => '42']],
        ];
    }
}
