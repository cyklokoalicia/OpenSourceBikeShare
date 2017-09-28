<?php

namespace Tests\Feature\RentService;

use BikeShare\Domain\Bike\BikeStatus;
use BikeShare\Domain\Rent\MethodType;
use BikeShare\Domain\Rent\RentStatus;
use BikeShare\Http\Services\AppConfig;
use BikeShare\Http\Services\Rents\RentService;
use BikeShare\Notifications\Sms\Rent\ForceRentOverrideRent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Notification;
use Tests\DbTestCaseWithSeeding;

class ForceRentTest extends DbTestCaseWithSeeding
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
        $this->appConfig = app(AppConfig::class);
        $this->rentService = new RentService($this->appConfig, MethodType::SMS);
    }

    /** @test */
    public function non_privileged_user_cannot_force_rent_bike()
    {
        $user = userWithResources();
        list($stand, $bike) = standWithBike();
        $this->expectException(AuthorizationException::class);
        $this->rentService->forceRentBike($user, $bike);
    }

    /** @test */
    public function admin_can_force_rent_non_occupied_bike()
    {
        $admin = adminWithResources();
        list($stand, $bike) = standWithBike();

        $bike->fresh();

        $this->rentService->forceRentBike($admin, $bike);

        self::assertEquals($bike->status, BikeStatus::OCCUPIED);
        self::assertNull($bike->stand);
        self::assertEquals($bike->user->id, $admin->id);
    }

    /** @test */
    public function admin_can_force_rent_occupied_bike()
    {
        $user = userWithResources();
        $admin = adminWithResources();
        list($stand, $bike) = standWithBike();

        Notification::fake();

        $userRent = $this->rentService->rentBike($user, $bike);
        $bike->fresh();

        self::assertEquals($bike->status, BikeStatus::OCCUPIED);
        self::assertNull($bike->stand);
        self::assertEquals($bike->user->id, $user->id);

        $adminRent = $this->rentService->forceRentBike($admin, $bike);
        $bike->fresh();

        self::assertEquals($bike->status, BikeStatus::OCCUPIED);
        self::assertNull($bike->stand);
        self::assertEquals($bike->user->id, $admin->id);

        self::assertEquals(RentStatus::CLOSE, $userRent->fresh()->status);
        self::assertEquals(RentStatus::OPEN, $adminRent->status);

        Notification::assertSentTo($user, ForceRentOverrideRent::class);
    }
}
