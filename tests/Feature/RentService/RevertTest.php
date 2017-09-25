<?php

namespace Tests\Feature\RentService;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Bike\BikeStatus;
use BikeShare\Domain\Rent\RentMethod;
use BikeShare\Domain\Rent\RentStatus;
use BikeShare\Domain\Rent\ReturnMethod;
use BikeShare\Domain\Stand\Stand;
use BikeShare\Domain\User\User;
use BikeShare\Http\Services\AppConfig;
use BikeShare\Http\Services\Rents\Exceptions\BikeNotRentedException;
use BikeShare\Http\Services\Rents\Exceptions\BikeRentedByOtherUserException;
use BikeShare\Http\Services\Rents\RentService;
use BikeShare\Notifications\Sms\Revert\BikeNotRented;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\DbTestCaseWithSeeding;
use Tests\TestCase;

class RevertTest extends DbTestCaseWithSeeding
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
    public function reverting_non_occupied_bike_throws_exception()
    {
        $admin = userWithResources([], true);
        list($stand, $bike) = standWithBike();
        $this->expectException(BikeNotRentedException::class);
        $this->rentService->revertBikeRent($admin, $bike, ReturnMethod::WEB);
    }

    /** @test */
    public function non_admin_user_reverting_bike_throws_exception()
    {
        $user = userWithResources();
        list($stand, $bike) = standWithBike();
        $this->rentService->rentBike($user, $bike, RentMethod::WEB);

        $this->expectException(AuthorizationException::class);
        $this->rentService->revertBikeRent($user, $bike, ReturnMethod::WEB);
    }

    /** @test */
    public function rent_and_revert_bike_all_ok()
    {
        // Arrange
        $user = userWithResources();
        $admin = userWithResources([], true);
        list($stand, $bike) = standWithBike();

        // Act
        $rent = $this->rentService->rentBike($user, $bike, RentMethod::WEB);

        // Assert
        self::assertEquals(BikeStatus::OCCUPIED, $bike->status);
        self::assertEquals($user->id, $rent->user->id);
        self::assertEquals($bike->id, $rent->bike->id);
        self::assertEquals(RentStatus::OPEN, $rent->status);

        // Act
        $rentAfterRevert = $this->rentService->revertBikeRent($admin, $bike, ReturnMethod::WEB);

        $bike->refresh();

        // Assert
        self::assertEquals(BikeStatus::FREE, $bike->status);
        self::assertEquals($stand->id, $bike->stand->id);
        self::assertNull($bike->user);
        self::assertEquals(RentStatus::CLOSE, $rentAfterRevert->status);

    }
}
