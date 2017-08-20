<?php

namespace Tests\Unit;

use BikeShare\Domain\Stand\Stand;
use BikeShare\Domain\User\User;
use BikeShare\Http\Services\AppConfig;
use BikeShare\Notifications\Sms\Credit;
use BikeShare\Notifications\Sms\StandInfo;
use Tests\TestCase;

/**
 * Notification tests that do not require DB migrations are placed here, others are in Feature directory
 */
class NotificationsTest extends TestCase
{
    /** @test */
    public function credit_notification_contains_user_credit()
    {
        $appConfig = $this->getMockBuilder(AppConfig::class)
            ->disableOriginalConstructor()
            ->getMock();
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
}
