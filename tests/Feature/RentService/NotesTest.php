<?php

namespace Tests\Feature\RentService;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Stand\Stand;
use BikeShare\Http\Services\AppConfig;
use BikeShare\Http\Services\Rents\RentService;
use BikeShare\Notifications\Admin\AllNotesDeleted;
use BikeShare\Notifications\Admin\BikeNoteAdded;
use BikeShare\Notifications\Admin\StandNoteAdded;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Notification;
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
    public function add_note_to_bike_ok()
    {
        // Arrange
        $user = userWithResources();
        $admin = userWithResources([], true);
        $bike = create(Stand::class)->bikes()->save(make(Bike::class));
        $noteText = "some note text";

        // Act
        Notification::fake();
        $this->rentService->addNoteToBike($bike, $user, $noteText);

        // Assert
        self::assertNotNull($bike->notes
            ->where('note', $noteText)
            ->where('notable_id', $bike->id)
            ->where('user_id', $user->id)->first());

        Notification::assertSentTo($admin, BikeNoteAdded::class);
    }

    /** @test */
    public function add_note_to_stand_ok()
    {
        // Arrange
        $user = userWithResources();
        $admin = userWithResources([], true);
        $stand = create(Stand::class);
        $noteText = "some note text";

        // Act
        Notification::fake();
        $this->rentService->addNoteToStand($stand, $user, $noteText);

        // Assert
        self::assertNotNull($stand->notes
            ->where('note', $noteText)
            ->where('notable_id', $stand->id)
            ->where('user_id', $user->id)->first());

        Notification::assertSentTo($admin, StandNoteAdded::class);
    }

    /** @test */
    public function add_notes_to_all_bikes_on_stand_ok()
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
    public function non_admin_user_deleting_note_from_bike_throws_exception()
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
    public function non_admin_user_delete_note_from_stand_throws_exception()
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


    /** @test */
    public function non_admin_user_delete_all_notes_from_stand_bikes_throws_exception()
    {
        // Arrange
        $user = userWithResources();
        $stand = create(Stand::class);
        for ($i=0; $i<=3; $i++){
            $stand->bikes()->save(make(Bike::class));
        }
        $noteText = "some note text";
        $this->rentService->addNoteToAllStandBikes($stand, $user, $noteText);

        // Act
        $this->expectException(AuthorizationException::class);

        $pattern = "ote";
        $this->rentService->deleteNoteFromAllStandBikes($stand, $user, $pattern);
    }

    /** @test */
    public function admin_can_delete_all_notes_from_stand_bikes_and_only_correct_notes_are_deleted()
    {
        // Arrange
        Notification::fake();
        $user = userWithResources();
        $admin = userWithResources([], true);

        $stand = create(Stand::class);
        for ($i=0; $i < 3; $i++){
            $stand->bikes()->save(make(Bike::class));
        }
        $noteText = "some note text";
        $pattern = "ote";
        $this->rentService->addNoteToAllStandBikes($stand, $user, $noteText);

        // Add the same note to other bike
        $otherBike = create(Bike::class);
        $this->rentService->addNoteToBike($otherBike, $user, $noteText);

        // Assert
        foreach ($stand->fresh()->bikes as $b){
            self::assertEquals(1, $b->notes->count());
        }

        // Act
        $deleted = $this->rentService->deleteNoteFromAllStandBikes($stand, $admin, $pattern);

        // Assert
        self::assertEquals(3, $deleted);

        foreach ($stand->fresh()->bikes as $b){
            self::assertEquals(0, $b->notes->count());
        }

        self::assertEquals(1, $otherBike->fresh()->notes->count());

        Notification::assertSentTo($admin, AllNotesDeleted::class);
    }

}
