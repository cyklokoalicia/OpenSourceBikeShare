<?php

namespace Tests\Unit;

use BikeShare\Domain\User\User;
use BikeShare\Http\Services\AppConfig;
use BikeShare\Notifications\Sms\Credit;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    /**
     * @test
     */
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
}
