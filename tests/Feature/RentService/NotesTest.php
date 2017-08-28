<?php

namespace Tests\Unit;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Stand\Stand;
use BikeShare\Http\Services\AppConfig;
use BikeShare\Http\Services\Rents\RentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\DbTestCaseWithSeeding;

class NotesTest extends DbTestCaseWithSeeding
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

    /** @test */
    public function normal_user_cannot_delete_note_from_bike()
    {
        $user = userWithResources();
        $bike = create(Stand::class)->bikes()->save(make(Bike::class));

        $noteText = "some note is here";
        $this->rentService->addNoteToBike($bike, $user, $noteText);

        $this->expectException(AuthorizationException::class);
        $this->rentService->deleteNoteFromBike($bike, $user, "note");

        $bike->refresh();
        self::assertEquals(1, $bike->notes->count());
    }

    /** @test */
    public function admin_can_delete_notes_from_bike()
    {
        // Arrange
        $user = userWithResources();
        $admin = userWithResources([], true);

        $stand = create(Stand::class);
        $bike = $stand->bikes()->save(make(Bike::class));
        $bike2 = $stand->bikes()->save(make(Bike::class));

        $noteText1 = "flatten wheel and stolen bell";
        $noteText2 = "wheel is missing";
        $noteText3 = "vandalized";

        // Act
        $this->rentService->addNoteToBike($bike, $user, $noteText1);
        $this->rentService->addNoteToBike($bike, $admin, $noteText2);
        $this->rentService->addNoteToBike($bike, $user, $noteText3);
        $this->rentService->addNoteToBike($bike2, $user, $noteText1);

        // Assert
        self::assertEquals(3, $bike->notes->count());

        // Act
        $count = $this->rentService->deleteNoteFromBike($bike, $admin, "wheel");

        // Test
        self::assertEquals(2, $count);

        self::assertEquals(1, $bike->fresh()->notes->count());
        self::assertEquals(1, $bike2->fresh()->notes->count());
    }

    /** @test */
    public function normal_user_cannot_delete_note_from_stand()
    {
        $user = userWithResources();
        $stand = create(Stand::class);

        $noteText = "some note is here";
        $this->rentService->addNoteToStand($stand, $user, $noteText);

        $this->expectException(AuthorizationException::class);
        $this->rentService->deleteNoteFromStand($stand, $user, "note");

        $stand->refresh();
        self::assertEquals(1, $stand->notes->count());
    }

    /** @test */
    public function admin_can_delete_notes_from_stand()
    {
        // Arrange
        $user = userWithResources();
        $admin = userWithResources([], true);

        $stand = create(Stand::class);

        $noteText1 = "flatten wheel and stolen bell";
        $noteText2 = "wheel is missing";
        $noteText3 = "vandalized";

        // Act
        $this->rentService->addNoteToStand($stand, $user, $noteText1);
        $this->rentService->addNoteToStand($stand, $user, $noteText2);
        $this->rentService->addNoteToStand($stand, $user, $noteText3);

        // Assert
        self::assertEquals(3, $stand->notes->count());

        // Act
        $count = $this->rentService->deleteNoteFromStand($stand, $admin, "wheel");

        // Test
        self::assertEquals(2, $count);
        $stand->refresh();
        self::assertEquals(1, $stand->notes->count());
    }

}
