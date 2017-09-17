<?php

namespace Tests\Feature\RentService;

use BikeShare\Domain\Bike\BikeStatus;
use BikeShare\Domain\Rent\RentStatus;
use BikeShare\Domain\Stand\Stand;
use BikeShare\Http\Services\AppConfig;
use BikeShare\Http\Services\Rents\RentService;
use BikeShare\Notifications\Sms\Rent\ForceRentOverrideRent;
use BikeShare\Notifications\Sms\Rent\ForceReturnOverrideRent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Notification;
use Tests\DbTestCaseWithSeeding;

class ForceReturnTest extends DbTestCaseWithSeeding
{
    use DatabaseMigrations;

    /**
     * @var RentService
     */
    private $rentService;

    /**
     * @var AppConfig
     */
    private $appConfig;

    protected function setUp()
    {
        parent::setUp();
        $this->rentService = app(RentService::class);
        $this->appConfig = app(AppConfig::class);
    }

    /** @test */
    public function non_privileged_user_cannot_force_return_bike()
    {
        $user = userWithResources();
        list($stand, $bike) = standWithBike([], ['bike_num' => 1]);
        $this->expectException(AuthorizationException::class);
        $this->rentService->forceReturnBike($user, $bike, $stand);
    }


    /** @test */
    public function admin_can_force_return_non_occupied_bike()
    {
        $admin = adminWithResources();
        list($stand, $bike) = standWithBike([], ['bike_num' => 1]);
        $stand2 = create(Stand::class);

        $this->rentService->forceReturnBike($admin, $bike, $stand2);

        $bike->refresh();
        self::assertEquals($stand2->id, $bike->stand->id);
    }

    /** @test */
    public function admin_can_force_return_occupied_bike()
    {
        $user = userWithResources();
        $admin = adminWithResources();
        list($stand, $bike) = standWithBike([], ['bike_num' => 1]);
        $stand2 = create(Stand::class);

        Notification::fake();

        $userRent = $this->rentService->rentBike($user, $bike);

        $bike->fresh();
        self::assertEquals($bike->user->id, $user->id);

        $this->rentService->forceReturnBike($admin, $bike, $stand2);

        $bike->fresh();
        self::assertEquals($bike->status, BikeStatus::FREE);
        self::assertEquals($stand2->id, $bike->stand->id);

        self::assertEquals(RentStatus::CLOSE, $userRent->fresh()->status);
        Notification::assertSentTo($user, ForceReturnOverrideRent::class);
    }
}