<?php
namespace Test\Feature;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Stand\Stand;
use BikeShare\Domain\User\User;
use BikeShare\Http\Services\AppConfig;
use BikeShare\Http\Services\Rents\Exceptions\RentException;
use BikeShare\Http\Services\Rents\Exceptions\RentExceptionType as RE;
use BikeShare\Http\Services\Rents\RentService;
use BikeShare\Http\Services\Sms\Receivers\SmsRequestContract;
use BikeShare\Notifications\Sms\BikeAlreadyRented;
use BikeShare\Notifications\Sms\BikeDoesNotExist;
use BikeShare\Notifications\Sms\BikeNotTopOfStack;
use BikeShare\Notifications\Sms\BikeRented;
use BikeShare\Notifications\Sms\Credit;
use BikeShare\Notifications\Sms\Free;
use BikeShare\Notifications\Sms\Help;
use BikeShare\Notifications\Sms\InvalidArgumentsCommand;
use BikeShare\Notifications\Sms\RechargeCredit;
use BikeShare\Notifications\Sms\RentLimitExceeded;
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

    /**
     * @var AppConfig
     */
    private $appConfig;

    protected function setUp()
    {
        parent::setUp();
        $this->smsRequest = app(SmsRequestContract::class);
        $this->appConfig = app(AppConfig::class);
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
    public function help_command_sent()
    {
        $user = factory(User::class)->create();
        Notification::fake();
        $this->get($this->buildSmsUrl($user, 'HELP'));
        Notification::assertSentTo($user, Help::class);
    }

    /**
     * @test
     */
    public function unknown_command_sent()
    {
        $user = factory(User::class)->create();
        Notification::fake();
        $this->get($this->buildSmsUrl($user, 'NOSUCHCOMMAND'));
        Notification::assertSentTo($user, UnknownCommand::class);
    }

    /**
     * @test
     */
    public function credit_command_sent()
    {
        $user = factory(User::class)->create();
        Notification::fake();
        $this->get($this->buildSmsUrl($user, 'CREDIT'));
        Notification::assertSentTo($user, Credit::class);
    }

    /**
     * @test
     */
    public function free_command_sent()
    {
        $user = factory(User::class)->create();
        Notification::fake();
        $this->get($this->buildSmsUrl($user, 'FREE'));
        Notification::assertSentTo($user, Free::class);
    }

    /**
     * @test
     */
    public function rent_command_missing_bike_number()
    {
        $user = factory(User::class)->create();
        Notification::fake();
        $this->get($this->buildSmsUrl($user, 'RENT'));
        Notification::assertSentTo($user, InvalidArgumentsCommand::class);
    }

    /**
     * @test
     */
    public function rent_command_non_existing_bike_number()
    {
        $user = factory(User::class)->create();
        factory(Stand::class)->create()->bikes()
            ->save(factory(Bike::class)->make(['bike_num'=>1]));
        Notification::fake();
        $this->get($this->buildSmsUrl($user, 'RENT 2'));
        Notification::assertSentTo($user, BikeDoesNotExist::class);
    }

    /**
     * @test
     */
    public function rent_command_ok()
    {
        $requiredCredit = $this->appConfig->getRequiredCredit();

        $user = factory(User::class)->create([
            'credit' => $requiredCredit,
            'limit' => 1
        ]);
        factory(Stand::class)->create()->bikes()
            ->save(factory(Bike::class)->make(['bike_num'=>1]));
        Notification::fake();
        $this->get($this->buildSmsUrl($user, 'RENT 1'));
        Notification::assertSentTo($user, BikeRented::class);
    }

    /**
     * @test
     */
    public function rent_command_low_credit()
    {
        $user = factory(User::class)->create();
        factory(Stand::class)->create()->bikes()
            ->save(factory(Bike::class)->make(['bike_num' => 1]));

        // fake low-credit
        $mockedRentService = $this->mockClass(RentService::class);
        $mockedRentService
            ->method('rentBike')
            ->willThrowException(new RentException(RE::LOW_CREDIT()));

        $this->app->instance(RentService::class, $mockedRentService);

        Notification::fake();
        $this->get($this->buildSmsUrl($user, 'RENT 1'));
        Notification::assertSentTo($user, RechargeCredit::class);
    }

    /**
     * @test
     */
    public function rent_command_bike_already_rented()
    {
        $user = factory(User::class)->create();
        factory(Stand::class)->create()->bikes()
            ->save(factory(Bike::class)->make(['bike_num' => 1]));

        // fake rented bike
        $mockedRentService = $this->mockClass(RentService::class);
        $mockedRentService
            ->method('rentBike')
            ->willThrowException(new RentException(RE::BIKE_NOT_FREE()));

        $this->app->instance(RentService::class, $mockedRentService);

        Notification::fake();
        $this->get($this->buildSmsUrl($user, 'RENT 1'));
        Notification::assertSentTo($user, BikeAlreadyRented::class);
    }

    /**
     * @test
     */
    public function rent_command_bike_not_top_of_stack()
    {
        $user = factory(User::class)->create();
        factory(Stand::class)->create()->bikes()
            ->save(factory(Bike::class)->make(['bike_num' => 1]));

        // fake rented bike
        $mockedRentService = $this->mockClass(RentService::class);
        $mockedRentService
            ->method('rentBike')
            ->willThrowException(new RentException(RE::BIKE_NOT_ON_TOP(), $this->mockClass(Bike::class)));

        $this->app->instance(RentService::class, $mockedRentService);

        Notification::fake();

        $this->get($this->buildSmsUrl($user, 'RENT 1'));
        Notification::assertSentTo($user, BikeNotTopOfStack::class);
    }

    /**
     * @test
     */
    public function rent_command_max_number_of_rents_exceeded()
    {
        $user = factory(User::class)->create();
        factory(Stand::class)->create()->bikes()
            ->save(factory(Bike::class)->make(['bike_num' => 1]));

        // fake rented bike
        $mockedRentService = $this->mockClass(RentService::class);
        $mockedRentService
            ->method('rentBike')
            ->willThrowException(new RentException(RE::MAXIMUM_NUMBER_OF_RENTS()));

        $this->app->instance(RentService::class, $mockedRentService);

        Notification::fake();

        $this->get($this->buildSmsUrl($user, 'RENT 1'));
        Notification::assertSentTo($user, RentLimitExceeded::class);
    }

    private function buildSmsUrl($user, $text)
    {
        $getParams = $this->smsRequest->buildGetQuery($text, $user->phone_number,1,1);
        return self::URL_PREFIX . '?' . $getParams;
    }

    private function mockClass($className)
    {
        return $this->getMockBuilder($className)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
