<?php

namespace Tests\Unit;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Stand\Stand;
use BikeShare\Http\Services\AppConfig;
use BikeShare\Http\Services\Rents\RentService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class RentServiceTest extends TestCase
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
    public function add_note_to_bike()
    {
        // Arrange
        $user = userWithResources();
        $bike = create(Stand::class)->bikes()->save(make(Bike::class));
        $noteText = "some note text";

        // Act
        $this->rentService->addNoteToBike($bike, $user, $noteText);

        // Assert
        self::assertNotNull($bike->notes
            ->where('note', $noteText)
            ->where('notable_id', $bike->id)
            ->where('user_id', $user->id)->first());
    }

    /** @test */
    public function add_note_to_stand()
    {
        // Arrange
        $user = userWithResources();
        $stand = create(Stand::class);
        $noteText = "some note text";

        // Act
        $this->rentService->addNoteToStand($stand, $user, $noteText);

        // Assert
        self::assertNotNull($stand->notes
            ->where('note', $noteText)
            ->where('notable_id', $stand->id)
            ->where('user_id', $user->id)->first());
    }

    /** @test */
    public function add_notes_to_all_bikes_on_stand()
    {
        // Arrange
        $user = userWithResources();
        $stand = create(Stand::class);
        for ($i=0; $i<=3; $i++){
            $stand->bikes()->save(make(Bike::class));
        }
        $noteText = "some note text";

        // Act
        $this->rentService->addNoteToAllStandBikes($stand, $user, $noteText);

//         Assert
        foreach ($stand->bikes as $bike){
            $note = $bike->notes
                ->where('note', $noteText)
                ->where('notable_id', $bike->id)
                ->where('user_id', $user->id)->first();
            self::assertNotNull($note);
        }
    }
}
