<?php
namespace Tests\Feature\SmsApi;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Stand\Stand;
use BikeShare\Notifications\Sms\BikeRentedSuccess;
use Notification;

class ForceRentCommandTest extends BaseSmsTest
{
    /** @test */
    public function renting_ok()
    {
        $admin = adminWithResources();
        standWithBike([], ['bike_num'=>1]);

        Notification::fake();
        $this->sendSms($admin, 'FORCERENT 1');

        Notification::assertSentTo($admin, BikeRentedSuccess::class);
    }

    /** @test */
    public function force_rent_with_low_credit_ok()
    {
        $admin = adminWithResources(['credit' => $this->appConfig->getRequiredCredit() - 1]);
        standWithBike([], ['bike_num'=>1]);

        Notification::fake();
        $this->sendSms($admin, 'FORCERENT 1');

        Notification::assertSentTo($admin, BikeRentedSuccess::class);
    }

    /** @test */
    public function force_rent_bike_already_rented_ok()
    {
        $user = userWithResources();
        $admin = adminWithResources();
        standWithBike([], ['bike_num'=>1]);

        Notification::fake();
        $this->sendSms($user, 'RENT 1');
        $this->sendSms($admin, 'FORCERENT 1');

        Notification::assertSentTo($user, BikeRentedSuccess::class);
        Notification::assertSentTo($admin, BikeRentedSuccess::class);
    }

    /** @test */
    public function force_rent_command_bike_not_top_of_stack_ok()
    {
        $admin = adminWithResources();
        $stand = create(Stand::class);
        $stand->bikes()->save(make(Bike::class, ['bike_num' => 1, 'stack_position'=>0]));
        $stand->bikes()->save(make(Bike::class, ['bike_num' => 2, 'stack_position'=>1]));
        config(['bike-share.stack_bike' => true]);

        Notification::fake();
        $this->sendSms($admin, 'FORCERENT 1');

        Notification::assertSentTo($admin, BikeRentedSuccess::class);
    }

    /** @test */
    public function force_rent_command_max_number_of_rents_exceeded_ok()
    {
        $admin = adminWithResources(['limit' => 0]);
        standWithBike([], ['bike_num'=>1]);

        Notification::fake();
        $this->sendSms($admin, 'FORCERENT 1');

        Notification::assertSentTo($admin, BikeRentedSuccess::class);
    }
}
