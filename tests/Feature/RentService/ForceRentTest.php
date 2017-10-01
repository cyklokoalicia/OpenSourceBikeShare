<?php

namespace Tests\Feature\RentService;

use BikeShare\Domain\Bike\BikeStatus;
use BikeShare\Domain\Rent\MethodType;
use BikeShare\Domain\Rent\RentStatus;
use BikeShare\Http\Services\AppConfig;
use BikeShare\Http\Services\Rents\ForceCommand;
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

        $rent = $this->rentService->forceRentBike($admin, $bike);

        self::assertEquals($bike->status, BikeStatus::OCCUPIED);
        self::assertNull($bike->stand);
        self::assertEquals($bike->user->id, $admin->id);
        self::assertTrue($rent->force_opened);
    }

    /** @test */
    public function admin_can_force_rent_occupied_bike()
    {
        // Arrange
        $user = userWithResources();
        $admin = adminWithResources();
        list($stand, $bike) = standWithBike();
        Notification::fake();

        // Act - rent bike
        $userRent = $this->rentService->rentBike($user, $bike);
        $bike->fresh();

        // Assert bike is rented
        self::assertEquals($bike->status, BikeStatus::OCCUPIED);
        self::assertNull($bike->stand);
        self::assertEquals($bike->user->id, $user->id);

        // Act - force rent
        $adminRent = $this->rentService->forceRentBike($admin, $bike);
        $bike->fresh();

        // Assert bike is rented
        self::assertEquals($bike->status, BikeStatus::OCCUPIED);
        self::assertNull($bike->stand);
        self::assertEquals($bike->user->id, $admin->id);
        self::assertEquals(RentStatus::OPEN, $adminRent->status);
        self::assertTrue($adminRent->force_opened);

        // Assert previous rent is closed and notification was sent
        $userRent->refresh();
        self::assertEquals(RentStatus::CLOSE, $userRent->status);
        self::assertTrue($userRent->force_closed);
        self::assertEquals(ForceCommand::rent($user)->forceCommand, $userRent->close_command);
        self::assertEquals($admin->id, $userRent->closed_by_user_id);

        Notification::assertSentTo($user, ForceRentOverrideRent::class);
    }
}
