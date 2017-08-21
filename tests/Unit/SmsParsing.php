<?php

namespace Tests\Unit;

use BikeShare\Domain\User\User;
use BikeShare\Http\Controllers\Api\v1\Sms\SmsController;
use BikeShare\Http\Services\AppConfig;
use BikeShare\Notifications\Sms\Credit;
use Tests\TestCase;

class SmsParsing extends TestCase
{
    /** @test */
    public function parse_sms_arguments()
    {
        // everything is uppercase after parsing
        $tests = [
            "CMD 0 ABC efg" => ['CMD', '0', 'ABC', 'EFG'],
            "CMD   1   ABC   efg" => ['CMD', '1', 'ABC', 'EFG'],
            "  CMD   2   ABC   efg  " => ['CMD', '2', 'ABC', 'EFG'],
            " \n CMD \n  3 \n  ABC \n  efg \n " => ['CMD', '3', 'ABC', 'EFG'],
            " \t\n CMD \t\n  4    \t\n  ABC  \t\n\n  efg \n\n " => ['CMD', '4', 'ABC', 'EFG'],
        ];
        foreach ($tests as $input => $expectedOutput){
            self::assertEquals($expectedOutput, SmsController::parseSmsArguments($input));
        }
    }

    /** @test */
    public function return_bike_sms_note_parsing()
    {
        // TODO fix the last assertion
        $tests = [
            'RETURN 0 STANDNAME somenote' => 'somenote',
            'RETURN 1 STANDNAME              somenote' => 'somenote',
            'RETURN 2 STANDNAME' => null,
            'RETURN 3 STANDNAME         ' => null,
            "RETURN 4 STANDNAME    \n     " => null,
            "RETURN 5 STANDNAME    \n   test  " => 'test',
            "RETURN 6 STANDNAME    \n   test\ttest  " => "test\ttest",
//            "RETURN 7 STANDNAME    \n   test\ntest  " => "test\ntest",
        ];

        foreach ($tests as $input => $expectedOutput){
            self::assertEquals($expectedOutput, SmsController::parseNoteFromReturnSms($input), $input);
        }
    }

    /** @test */
    public function sms_note_parsing()
    {
        $tests = [
            'note 0 somenote' => 'somenote',
            'NOTE STANDNAME   somenote' => 'somenote',
            'Note 2 DACO STANDNAME' => 'DACO STANDNAME',
        ];

        foreach ($tests as $input => $expectedOutput){
            self::assertEquals($expectedOutput, SmsController::parseNoteFromNoteSms($input), $input);
        }
    }
}
