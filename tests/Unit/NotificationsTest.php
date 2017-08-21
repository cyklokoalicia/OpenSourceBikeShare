<?php

namespace Tests\Unit;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Stand\Stand;
use BikeShare\Domain\User\User;
use BikeShare\Http\Services\AppConfig;
use BikeShare\Notifications\Sms\Credit;
use BikeShare\Notifications\Sms\Help;
use BikeShare\Notifications\Sms\NoteForBikeSaved;
use BikeShare\Notifications\Sms\NoteForStandSaved;
use BikeShare\Notifications\Sms\StandInfo;
use Tests\TestCase;

/**
 * Notification tests that do not require DB migrations are placed here, others are in Feature directory
 */
class NotificationsTest extends TestCase
{
    /** @test */
    public function help_notification_contains_commands()
    {
        $appConfig = $this->mockObj(AppConfig::class);
        $appConfig->method('isCreditEnabled')->willReturn(true);
        $user = $this->mockObj(User::class);
        $user->method('hasRole')->willReturn(true);

        $text = (new Help($user, $appConfig))->text();
        // test at least these basic commands
        foreach (['CREDIT', 'RENT', 'RETURN', 'FORCERENT', 'FORCERETURN', 'REVERT'] as $cmd){
            self::assertContains($cmd, $text);
        }
    }

    /** @test */
    public function credit_notification_contains_user_credit()
    {
        $appConfig = $this->mockObj(AppConfig::class);
        $appConfig->method('getCreditCurrency')->willReturn('$');

        $user = factory(User::class)->make([]);
        $notification = new Credit($appConfig, $user);
        self::assertContains((string) $user->credit, $notification->text());
    }

    /** @test */
    public function info_notification_contains_stand_name_description_and_possibly_gps()
    {
        $stand = make(Stand::class);
        $notifText = (new StandInfo($stand))->text();
        self::assertContains($stand->name, $notifText);
        self::assertContains($stand->description, $notifText);
        self::assertContains('GPS', $notifText);

        $standNoGps = make(Stand::class, ['name'=>'XYZ', 'description' => 'desc', 'longitude' => null, 'latitude' => null]);
        $notifText2 = (new StandInfo($standNoGps))->text();
        self::assertContains($standNoGps->name, $notifText2);
        self::assertContains($standNoGps->description, $notifText2);
        self::assertNotContains('GPS', $notifText2);
    }

    /** @test */
    public function note_notification_to_bike_contains_bike_number()
    {
        $bike = make(Bike::class);
        $text = (new NoteForBikeSaved($bike))->text();
        self::assertContains((string) $bike->bike_num, $text);
    }

    /** @test */
    public function note_notification_to_stand_contains_stand_name()
    {
        $stand = make(Stand::class);
        $text = (new NoteForStandSaved($stand))->text();
        self::assertContains((string) $stand->name, $text);
    }

    private function mockObj($className)
    {
        return $this->getMockBuilder($className)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
