<?php
namespace Tests\Feature\SmsApi;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Stand\Stand;
use BikeShare\Domain\User\User;
use BikeShare\Notifications\Admin\AllNotesDeleted;
use BikeShare\Notifications\Admin\NotesDeleted;
use BikeShare\Notifications\Sms\BikeAlreadyRented;
use BikeShare\Notifications\Sms\BikeDoesNotExist;
use BikeShare\Notifications\Sms\BikeNotTopOfStack;
use BikeShare\Notifications\Sms\BikeRentedSuccess;
use BikeShare\Notifications\Sms\BikeReturnedSuccess;
use BikeShare\Notifications\Sms\BikeToReturnNotRentedByMe;
use BikeShare\Notifications\Sms\Credit;
use BikeShare\Notifications\Sms\Free;
use BikeShare\Notifications\Sms\Help;
use BikeShare\Notifications\Sms\InvalidArgumentsCommand;
use BikeShare\Notifications\Sms\NoBikesRented;
use BikeShare\Notifications\Sms\NoBikesUntagged;
use BikeShare\Notifications\Sms\NoNotesDeleted;
use BikeShare\Notifications\Sms\NoteForBikeSaved;
use BikeShare\Notifications\Sms\NoteForStandSaved;
use BikeShare\Notifications\Sms\NoteTextMissing;
use BikeShare\Notifications\Sms\RechargeCredit;
use BikeShare\Notifications\Sms\RentLimitExceeded;
use BikeShare\Notifications\Sms\StandDoesNotExist;
use BikeShare\Notifications\Sms\StandInfo;
use BikeShare\Notifications\Sms\StandListBikes;
use BikeShare\Notifications\Sms\TagForStandSaved;
use BikeShare\Notifications\Sms\Unauthorized;
use BikeShare\Notifications\Sms\UnknownCommand;
use BikeShare\Notifications\Sms\WhereIsBike;
use Notification;

class NoteCommandsTest extends BaseSmsTest
{
    /** @test */
    public function note_command_non_existing_bike_and_stand()
    {
        $user = create(User::class);

        Notification::fake();

        $this->sendSms($user, 'NOTE 1 xyz');
        Notification::assertSentTo($user, BikeDoesNotExist::class);

        $this->sendSms($user, 'NOTE ABCD xyz');
        Notification::assertSentTo($user, StandDoesNotExist::class);
    }

    /** @test */
    public function note_command_missing_note_text()
    {
        $user = create(User::class);

        Notification::fake();

        $this->sendSms($user, 'NOTE 1');
        Notification::assertSentTo($user, NoteTextMissing::class);
    }

    /** @test */
    public function note_command_note_to_bike_ok()
    {
        $user = create(User::class);
        create(Stand::class)->bikes()->save(make(Bike::class, ['bike_num'=>1]));

        Notification::fake();
        $this->sendSms($user, 'NOTE 1 note text is here');

        Notification::assertSentTo($user, NoteForBikeSaved::class);
    }

    /** @test */
    public function note_command_note_to_stand_ok()
    {
        $user = create(User::class);
        create(Stand::class, ['name' => 'SAFKO']);

        Notification::fake();
        $this->sendSms($user, 'NOTE SAFKO note text is here');

        Notification::assertSentTo($user, NoteForStandSaved::class);
    }

    /** @test */
    public function tag_command_ok()
    {
        $user = create(User::class);
        create(Stand::class, ['name' => 'SAFKO'])->bikes()->save(make(Bike::class, ['bike_num'=>1]));

        Notification::fake();
        $this->sendSms($user, 'TAG SAFKO note text is here');

        Notification::assertSentTo($user, TagForStandSaved::class);
    }


    /** @test */
    public function delnote_command_from_bike_not_authorized()
    {
        $user = create(User::class);
        create(Stand::class)->bikes()->save(make(Bike::class, ['bike_num'=>1]));

        Notification::fake();
        $this->sendSms($user, 'DELNOTE 1 something');

        Notification::assertSentTo($user, Unauthorized::class);
    }

    /** @test */
    public function delnote_command_from_bike_without_notes()
    {
        $admin = userWithResources([], true);
        create(Stand::class)->bikes()->save(make(Bike::class, ['bike_num'=>1]));

        Notification::fake();
        $this->sendSms($admin, 'DELNOTE 1 something');

        Notification::assertSentTo($admin, NoNotesDeleted::class);
    }


    /** @test */
    public function delnote_command_from_bike_ok()
    {
        $user = userWithResources();
        $admin = userWithResources([], true);
        create(Stand::class)->bikes()->save(make(Bike::class, ['bike_num'=>1]));

        Notification::fake();
        $this->sendSms($user, 'NOTE 1 note_text is here');
        $this->sendSms($admin, 'DELNOTE 1 note_text');
        $this->sendSms($admin, 'DELNOTE 1 note_text');
        $this->sendSms($admin, 'DELNOTE 2 something');

        Notification::assertSentTo($user, NoteForBikeSaved::class);
        Notification::assertSentTo($admin, NotesDeleted::class);
        Notification::assertSentTo($admin, NoNotesDeleted::class);
        Notification::assertSentTo($admin, BikeDoesNotExist::class);
    }

    /** @test */
    public function delnote_command_from_stand_not_authorized()
    {
        $user = create(User::class);
        create(Stand::class, ['name' => 'SAFKO']);

        Notification::fake();
        $this->sendSms($user, 'DELNOTE SAFKO something');

        Notification::assertSentTo($user, Unauthorized::class);
    }

    /** @test */
    public function delnote_command_from_stand_ok()
    {
        $admin = userWithResources([], true);
        create(Stand::class, ['name' => 'SAFKO']);

        Notification::fake();
        $this->sendSms($admin, 'NOTE SAFKO poznamka text is here');
        $this->sendSms($admin, 'DELNOTE SAFKO poznamka');
        $this->sendSms($admin, 'DELNOTE SAFKO poznamka');
        $this->sendSms($admin, 'DELNOTE SAFKO2 something');

        Notification::assertSentTo($admin, NoteForStandSaved::class);
        Notification::assertSentTo($admin, NotesDeleted::class);
        Notification::assertSentTo($admin, NoNotesDeleted::class);
        Notification::assertSentTo($admin, StandDoesNotExist::class);
    }

    /** @test */
    public function untag_command_ok()
    {
        $user = userWithResources();
        $admin = userWithResources([], true);
        create(Stand::class, ['name' => 'SAFKO'])->bikes()->save(make(Bike::class, ['bike_num'=>1]));
        create(Stand::class, ['name' => 'MANDERLAK'])->bikes()->save(make(Bike::class, ['bike_num'=>2]));

        Notification::fake();

        // Tag SAFKO
        $this->sendSms($user, 'TAG SAFKO note text is here');
        Notification::assertSentTo($user, TagForStandSaved::class);

        $this->sendSms($admin, 'UNTAG SAFKO text');
        Notification::assertSentTo($admin, AllNotesDeleted::class);

        $this->sendSms($admin, 'UNTAG SAFKO text');
        Notification::assertSentTo($admin, NoBikesUntagged::class);

        $this->sendSms($admin, 'UNTAG SAFKO2');
        Notification::assertSentTo($admin, NoBikesUntagged::class);

        // Tag MANDERLAK
        $this->sendSms($admin, 'TAG MANDERLAK now something completely different');
        Notification::assertSentTo($admin, TagForStandSaved::class);

        $this->sendSms($admin, 'UNTAG MANDERLAK');
        Notification::assertSentTo($admin, AllNotesDeleted::class);

        $this->sendSms($admin, 'UNTAG MANDERLAK');
        Notification::assertSentTo($admin, NoBikesUntagged::class);
    }
}
