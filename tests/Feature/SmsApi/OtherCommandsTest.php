<?php
namespace Test\Feature\SmsApi;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Stand\Stand;
use BikeShare\Domain\User\User;
use BikeShare\Notifications\Sms\BikeDoesNotExist;
use BikeShare\Notifications\Sms\Credit;
use BikeShare\Notifications\Sms\Free;
use BikeShare\Notifications\Sms\Help;
use BikeShare\Notifications\Sms\StandDoesNotExist;
use BikeShare\Notifications\Sms\StandInfo;
use BikeShare\Notifications\Sms\StandListBikes;
use BikeShare\Notifications\Sms\UnknownCommand;
use BikeShare\Notifications\Sms\WhereIsBike;
use Notification;

class OtherCommandsTest extends BaseSmsTest
{
    /** @test */
    public function missing_parameters()
    {
        $response = $this->get(self::URL_PREFIX);
        $response->assertStatus(400);
    }

    /** @test */
    public function sms_from_non_registered_number_fails()
    {
        // just create user, do not save in DB
        $user = make(User::class, ['phone_number' => 'non_existing_number']);
        $response = $this->sendSms($user, 'text');
        $response->assertStatus(400);
    }

    /** @test */
    public function help_command_ok()
    {
        $user = create(User::class);
        Notification::fake();
        $this->sendSms($user, 'HELP');
        Notification::assertSentTo($user, Help::class);
    }

    /** @test */
    public function unknown_command_ok()
    {
        $user = create(User::class);
        Notification::fake();
        $this->sendSms($user, 'NO_SUCH_COMMAND');
        Notification::assertSentTo($user, UnknownCommand::class);
    }

    /** @test */
    public function credit_command_ok()
    {
        $user = create(User::class);
        Notification::fake();
        $this->sendSms($user, 'CREDIT');
        Notification::assertSentTo($user, Credit::class);
    }

    /** @test */
    public function free_command_ok()
    {
        $user = create(User::class);
        Notification::fake();
        $this->sendSms($user, 'FREE');
        Notification::assertSentTo($user, Free::class);
    }

    /** @test */
    public function where_command_non_existing_bike_number()
    {
        $user = create(User::class);
        create(Stand::class)->bikes()->save(make(Bike::class, ['bike_num'=>1]));

        Notification::fake();
        $this->sendSms($user, 'WHERE 2');

        Notification::assertSentTo($user, BikeDoesNotExist::class);
    }

    /** @test */
    public function where_command_ok()
    {
        $user = create(User::class);
        standWithBike([], ['bike_num'=>1]);

        Notification::fake();
        $this->sendSms($user, 'WHERE 1');
        Notification::assertSentTo($user, WhereIsBike::class);

        $this->sendSms($user, 'WHO 1');
        Notification::assertSentTo($user, WhereIsBike::class);
    }

    /** @test */
    public function info_command_non_existing_stand_name()
    {
        $user = create(User::class);

        Notification::fake();
        $this->sendSms($user, 'INFO NONEXISTING');

        Notification::assertSentTo($user, StandDoesNotExist::class);
    }

    /** @test */
    public function info_command_ok()
    {
        $stand = create(Stand::class);
        $user = create(User::class);

        Notification::fake();
        $this->sendSms($user, 'INFO ' . $stand->name);

        Notification::assertSentTo($user, StandInfo::class);
    }

    /** @test */
    public function list_command_ok()
    {
        $user = userWithResources();
        standWithBike(['name' => 'SAFKO'], ['bike_num' => 1]);

        Notification::fake();
        $this->sendSms($user, 'LIST SAFKO');

        Notification::assertSentTo($user, StandListBikes::class);
    }
}
