<?php
namespace Tests\Feature\SmsApi;

use BikeShare\Notifications\Sms\Revert\BikeNotRented;
use BikeShare\Notifications\Sms\Revert\RevertSuccess;
use BikeShare\Notifications\Sms\Unauthorized;
use Notification;

class RevertCommandTest extends BaseSmsTest
{

    /** @test */
    public function reverting_non_occupied_bike_should_give_error_notification()
    {
        $admin = adminWithResources();
        standWithBike([], ['bike_num' => 1]);

        Notification::fake();
        $this->sendSms($admin, 'REVERT 1');

        Notification::assertSentTo($admin, BikeNotRented::class);
    }

    /** @test */
    public function reverting_bike_as_non_admin_user_should_give_authorization_error_notification()
    {
        $user = userWithResources();
        standWithBike([], ['bike_num' => 1]);
        $this->sendSms($user, 'RENT 1');

        Notification::fake();
        $this->sendSms($user, 'REVERT 1');

        Notification::assertSentTo($user, Unauthorized::class);
    }

    /** @test */
    public function reverting_bike_ok_should_give_success_notification()
    {
        $user = userWithResources();
        $admin = adminWithResources();
        standWithBike([], ['bike_num' => 1]);
        $this->sendSms($user, 'RENT 1');

        Notification::fake();
        $this->sendSms($admin, 'REVERT 1');

        Notification::assertSentTo($admin, RevertSuccess::class);
    }

}
