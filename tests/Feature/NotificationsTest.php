<?php
namespace Test\Feature;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Stand\Stand;
use BikeShare\Notifications\Sms\Free;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use DatabaseMigrations;

    /**
     * @test
     */
    public function free_notification_contains_valid_data()
    {
        $emptyStands = factory(Stand::class, 2)->create();
        $standsWithBike = factory(Stand::class, 3)
            ->create()->each(function ($stand){
                $stand->bikes()->save(factory(Bike::class)->make());
            });
        $standsWithTwoBikes = factory(Stand::class, 4)
            ->create()
            ->each(function ($stand){
                factory(Bike::class, 2)
                    ->make()
                    ->each(function ($bike) use ($stand){
                    $stand->bikes()->save($bike);
                });
            });

        $notificationText = app(Free::class)->text();

        foreach ($emptyStands as $stand){
            self::assertContains($stand->name, $notificationText);
            // it doesn't contain
            self::assertNotContains($stand->name . ':', $notificationText);
        }

        foreach ($standsWithBike as $stand){
            self::assertContains($stand->name . ":1", $notificationText);
        }

        foreach ($standsWithTwoBikes as $stand){
            self::assertContains($stand->name . ":2", $notificationText);
        }
    }
}
