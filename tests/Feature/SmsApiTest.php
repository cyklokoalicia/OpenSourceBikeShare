<?php
namespace Test\Feature;

use BikeShare\Domain\User\User;
use BikeShare\Http\Services\AppConfig;
use BikeShare\Http\Services\Sms\Receivers\SmsRequestContract;
use BikeShare\Notifications\Sms\Credit;
use BikeShare\Notifications\Sms\Help;
use BikeShare\Notifications\Sms\UnknownCommand;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Notification;
use Tests\TestCase;

class SmsApiTest extends TestCase
{
    use DatabaseMigrations;

    const URL_PREFIX = '/api/sms/receive';

    /**
     * @var SmsRequestContract
     */
    private $smsRequest;

    protected function setUp()
    {
        parent::setUp();
        $this->smsRequest = $this->app->make(SmsRequestContract::class);
    }

    /**
     * @test
     */
    public function missing_parameters()
    {
        $response = $this->get(self::URL_PREFIX);
        $response->assertStatus(400);
    }

    /**
     * @test
     */
    public function non_existing_number()
    {
        $getParams = $this->smsRequest->buildGetQuery('text','non_existing_number',1,1);
        $response = $this->get(self::URL_PREFIX . '?' . $getParams);
        $response->assertStatus(400);
    }

    /**
     * @test
     */
    public function help_command()
    {
        $user = factory(User::class)->create();
        $getParams = $this->smsRequest->buildGetQuery('HELP', $user->phone_number,1,1);
        Notification::fake();
        $this->get(self::URL_PREFIX . '?' . $getParams);
        Notification::assertSentTo($user, Help::class);
    }

    /**
     * @test
     */
    public function unknown_command()
    {
        $user = factory(User::class)->create();
        $getParams = $this->smsRequest->buildGetQuery('NOSUCHCOMMAND',$user->phone_number,1,1);
        Notification::fake();
        $this->get(self::URL_PREFIX . '?' . $getParams);
        Notification::assertSentTo($user, UnknownCommand::class);
    }

    /**
     * @test
     */
    public function credit_command()
    {
        $user = factory(User::class)->create();
        $getParams = $this->smsRequest->buildGetQuery('CREDIT',$user->phone_number,1,1);
        Notification::fake();
        $this->get(self::URL_PREFIX . '?' . $getParams);

        Notification::assertSentTo($user, Credit::class);
    }


}
