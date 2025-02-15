<?php

declare(strict_types=1);

namespace Test\BikeShare\Unit\SmsCommand;

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
            'command' => 'RETURN 42 MAINSQUARE',
            'expected' => ['command' => 'RETURN', 'arguments' => ['bikeNumber' => '42', 'standName' => 'MAINSQUARE', 'note' => null]],
        ];
        yield 'RETURN with note' => [
            'command' => 'RETURN 42 MAINSQUARE broken',
            'expected' => ['command' => 'RETURN', 'arguments' => ['bikeNumber' => '42', 'standName' => 'MAINSQUARE', 'note' => 'broken']],
        ];
        yield 'RETURN without stand' => [
            'command' => 'RETURN 42',
            'expected' => ['command' => 'UNKNOWN', 'possibleCommand' => 'RETURN' ,'arguments' => []],
        ];
        yield 'WHERE' => [
            'command' => 'WHERE 42',
            'expected' => ['command' => 'WHERE', 'arguments' => ['bikeNumber' => '42']],
        ];
        yield 'INFO' => [
            'command' => 'INFO MAINSQUARE',
            'expected' => ['command' => 'INFO', 'arguments' => ['standName' => 'MAINSQUARE']],
        ];
        yield 'NOTE bike' => [
            'command' => 'NOTE 42 broken',
            'expected' => ['command' => 'NOTE', 'arguments' => ['bikeNumber' => '42', 'note' => 'broken']],
        ];
        yield 'NOTE standName' => [
            'command' => 'NOTE MAINSQUARE broken',
            'expected' => ['command' => 'NOTE', 'arguments' => ['standName' => 'MAINSQUARE', 'note' => 'broken']],
        ];
        yield 'FORCERENT' => [
            'command' => 'FORCERENT 42',
            'expected' => ['command' => 'FORCERENT', 'arguments' => ['bikeNumber' => '42']],
        ];
        yield 'FORCERETURN' => [
            'command' => 'FORCERETURN 42 MAINSQUARE',
            'expected' => ['command' => 'FORCERETURN', 'arguments' => ['bikeNumber' => '42', 'standName' => 'MAINSQUARE', 'note' => null]],
        ];
        yield 'FORCERETURN with note' => [
            'command' => 'FORCERETURN 42 MAINSQUARE broken',
            'expected' => ['command' => 'FORCERETURN', 'arguments' => ['bikeNumber' => '42', 'standName' => 'MAINSQUARE', 'note' => 'broken']],
        ];
        yield 'FORCERETURN without stand' => [
            'command' => 'FORCERETURN 42 ',
            'expected' => ['command' => 'UNKNOWN', 'possibleCommand' => 'FORCERETURN' ,'arguments' => []],
        ];
        yield 'LIST' => [
            'command' => 'LIST MAINSQUARE',
            'expected' => ['command' => 'LIST', 'arguments' => ['standName' => 'MAINSQUARE']],
        ];
        yield 'LIST lowecase' => [
            'command' => 'LIST MAINSQUARE',
            'expected' => ['command' => 'LIST', 'arguments' => ['standName' => 'MAINSQUARE']],
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
            'command' => 'TAG MAINSQUARE broken',
            'expected' => ['command' => 'TAG', 'arguments' => ['standName' => 'MAINSQUARE', 'note' => 'broken']],
        ];
        yield 'UNTAG all' => [
            'command' => 'UNTAG MAINSQUARE',
            'expected' => ['command' => 'UNTAG', 'arguments' => ['standName' => 'MAINSQUARE', 'pattern' => null]],
        ];
        yield 'UNTAG pattern' => [
            'command' => 'UNTAG MAINSQUARE broken',
            'expected' => ['command' => 'UNTAG', 'arguments' => ['standName' => 'MAINSQUARE', 'pattern' => 'broken']],
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
            'command' => 'RENT MAINSQUARE 42',
            'expected' => ['command' => 'UNKNOWN', 'possibleCommand' => 'RENT' ,'arguments' => []],
        ];
        yield 'rent' => [
            'command' => 'rent 42',
            'expected' => ['command' => 'RENT', 'arguments' => ['bikeNumber' => '42']],
        ];
    }
}
