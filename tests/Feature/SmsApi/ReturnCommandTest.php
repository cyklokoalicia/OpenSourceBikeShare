<?php
namespace Test\Feature\SmsApi;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Stand\Stand;
use BikeShare\Domain\User\User;
use BikeShare\Notifications\Sms\BikeDoesNotExist;
use BikeShare\Notifications\Sms\BikeReturnedSuccess;
use BikeShare\Notifications\Sms\BikeToReturnNotRentedByMe;
use BikeShare\Notifications\Sms\InvalidArgumentsCommand;
use BikeShare\Notifications\Sms\NoBikesRented;
use BikeShare\Notifications\Sms\StandDoesNotExist;
use Notification;

class ReturnCommandTest extends BaseSmsTest
{
    /** @test */
    public function return_command_stand_does_not_exist()
    {
        $user = create(User::class);
        standWithBike(['name'=>'ABCD'], ['bike_num'=>1]);

        Notification::fake();
        $this->sendSms($user, 'RETURN 1 NO_SUCH_STAND');

        Notification::assertSentTo($user, StandDoesNotExist::class);
    }

    /** @test */
    public function return_command_missing_stand_name()
    {
        $user = create(User::class);

        Notification::fake();
        $this->sendSms($user, 'RETURN 1');

        Notification::assertSentTo($user, InvalidArgumentsCommand::class);
    }

    /** @test */
    public function return_command_bike_does_not_exist()
    {
        $user = create(User::class);
        standWithBike(['name'=>'ABCD'], ['bike_num'=>1]);

        Notification::fake();
        $this->sendSms($user, 'RETURN 2 ABCD');

        Notification::assertSentTo($user, BikeDoesNotExist::class);
    }

    /** @test */
    public function return_command_bike_not_rented_or_rented_by_other_user()
    {

        $user = userWithResources();
        $otherUser = userWithResources();
        $stand = create(Stand::class, ['name' => 'SAFKO']);
        $stand->bikes()->save(make(Bike::class, ['bike_num'=>1]));
        $stand->bikes()->save(make(Bike::class, ['bike_num'=>2]));
        $stand->bikes()->save(make(Bike::class, ['bike_num'=>3]));

        // Assert 1
        Notification::fake();
        $this->sendSms($user, 'RETURN 2 SAFKO');
        Notification::assertSentTo($user, NoBikesRented::class);

        // Act
        $this->sendSms($otherUser, 'RENT 1');
        $this->sendSms($user, 'RENT 2');

        // Assert 2
        $this->sendSms($user, 'RETURN 1 SAFKO');
        Notification::assertSentTo($user, BikeToReturnNotRentedByMe::class);

        $this->sendSms($user, 'RETURN 3 SAFKO');
        Notification::assertSentTo($user, BikeToReturnNotRentedByMe::class);
    }

    /** @test */
    public function return_command_ok()
    {
        $user = userWithResources();
        standWithBike(['name'=>'SAFKO'], ['bike_num'=>1]);

        $this->sendSms($user, 'RENT 1');

        Notification::fake();
        $this->sendSms($user, 'RETURN 1 SAFKO');

        Notification::assertSentTo($user, BikeReturnedSuccess::class);
    }
}
