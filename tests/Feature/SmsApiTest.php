<?php
namespace Test\Feature;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Stand\Stand;
use BikeShare\Domain\User\User;
use BikeShare\Http\Services\AppConfig;
use BikeShare\Http\Services\Sms\Receivers\SmsRequestContract;
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
use BikeShare\Notifications\Sms\TagForStandSaved;
use BikeShare\Notifications\Sms\Unauthorized;
use BikeShare\Notifications\Sms\UnknownCommand;
use BikeShare\Notifications\Sms\WhereIsBike;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Notification;
use Tests\DbTestCaseWithSeeding;

class SmsApiTest extends DbTestCaseWithSeeding
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

    /** @test */
    public function missing_parameters()
    {
        $response = $this->get(self::URL_PREFIX);
        $response->assertStatus(400);
    }

    /** @test */
    public function non_existing_number()
    {
        $getParams = $this->smsRequest->buildGetQuery('text','non_existing_number',1,1);
        $response = $this->get(self::URL_PREFIX . '?' . $getParams);
        $response->assertStatus(400);
    }

    /** @test */
    public function help_command_sent()
    {
        $user = factory(User::class)->create();
        Notification::fake();
        $this->get($this->buildSmsUrl($user, 'HELP'));
        Notification::assertSentTo($user, Help::class);
    }

    /** @test */
    public function unknown_command_sent()
    {
        $user = factory(User::class)->create();
        Notification::fake();
        $this->get($this->buildSmsUrl($user, 'NOSUCHCOMMAND'));
        Notification::assertSentTo($user, UnknownCommand::class);
    }

    /** @test */
    public function credit_command_sent()
    {
        $user = factory(User::class)->create();
        Notification::fake();
        $this->get($this->buildSmsUrl($user, 'CREDIT'));
        Notification::assertSentTo($user, Credit::class);
    }

    /** @test */
    public function free_command_sent()
    {
        $user = factory(User::class)->create();

        Notification::fake();
        $this->get($this->buildSmsUrl($user, 'FREE'));

        Notification::assertSentTo($user, Free::class);
    }

    /** @test */
    public function rent_command_missing_bike_number()
    {
        $user = factory(User::class)->create();

        Notification::fake();
        $this->get($this->buildSmsUrl($user, 'RENT'));

        Notification::assertSentTo($user, InvalidArgumentsCommand::class);
    }

    /** @test */
    public function rent_command_non_existing_bike_number()
    {
        $user = create(User::class);
        create(Stand::class)->bikes()->save(make(Bike::class, ['bike_num'=>1]));

        Notification::fake();
        $this->get($this->buildSmsUrl($user, 'RENT 2'));

        Notification::assertSentTo($user, BikeDoesNotExist::class);
    }

    /** @test */
    public function rent_command_ok()
    {
        $user = userWithResources();
        create(Stand::class)->bikes()->save(make(Bike::class, ['bike_num'=>1]));

        Notification::fake();
        $this->get($this->buildSmsUrl($user, 'RENT 1'));

        Notification::assertSentTo($user, BikeRentedSuccess::class);
    }

    /** @test */
    public function rent_command_low_credit()
    {
        $user = create(User::class, ['credit' => $this->appConfig->getRequiredCredit() - 1, 'limit' => 1]);
        create(Stand::class)->bikes()->save(make(Bike::class, ['bike_num'=>1]));

        Notification::fake();
        $this->get($this->buildSmsUrl($user, 'RENT 1'));

        Notification::assertSentTo($user, RechargeCredit::class);
    }

    /** @test */
    public function rent_command_bike_already_rented()
    {
        $user = userWithResources();
        create(Stand::class)->bikes()->save(make(Bike::class, ['bike_num'=>1]));

        Notification::fake();
        $this->get($this->buildSmsUrl($user, 'RENT 1'));
        $this->get($this->buildSmsUrl($user, 'RENT 1'));

        Notification::assertSentTo($user, BikeAlreadyRented::class);
    }

    /** @test */
    public function rent_command_bike_not_top_of_stack()
    {
        $user = userWithResources();
        $stand = create(Stand::class);
        $stand->bikes()->save(make(Bike::class, ['bike_num' => 1, 'stack_position'=>0]));
        $stand->bikes()->save(make(Bike::class, ['bike_num' => 2, 'stack_position'=>1]));
        config(['bike-share.stack_bike' => true]);

        Notification::fake();
        $this->get($this->buildSmsUrl($user, 'RENT 1'));

        Notification::assertSentTo($user, BikeNotTopOfStack::class);
    }

    /** @test */
    public function rent_command_max_number_of_rents_exceeded()
    {
        $user = create(User::class, ['credit' => $this->appConfig->getRequiredCredit(), 'limit' => 0]);
        create(Stand::class)->bikes()->save(make(Bike::class, ['bike_num'=>1]));

        Notification::fake();
        $this->get($this->buildSmsUrl($user, 'RENT 1'));

        Notification::assertSentTo($user, RentLimitExceeded::class);
    }

    /** @test */
    public function return_command_stand_does_not_exist()
    {
        $user = create(User::class);
        create(Stand::class, ['name'=>'ABCD'])->bikes()->save(make(Bike::class, ['bike_num'=>1]));

        Notification::fake();
        $this->get($this->buildSmsUrl($user, 'RETURN 1 NO_SUCH_STAND'));

        Notification::assertSentTo($user, StandDoesNotExist::class);
    }

    /** @test */
    public function return_command_missing_stand_name()
    {
        $user = create(User::class);

        Notification::fake();
        $this->get($this->buildSmsUrl($user, 'RETURN 1'));

        Notification::assertSentTo($user, InvalidArgumentsCommand::class);
    }

    /** @test */
    public function return_command_bike_does_not_exist()
    {
        $user = create(User::class);
        create(Stand::class, ['name'=>'ABCD'])->bikes()->save(make(Bike::class, ['bike_num'=>1]));

        Notification::fake();
        $this->get($this->buildSmsUrl($user, 'RETURN 2 ABCD'));

        Notification::assertSentTo($user, BikeDoesNotExist::class);
    }

    /** @test */
    public function return_command_bike_not_rented_or_rented_by_other_user()
    {

        $user = userWithResources();
        $otherUser = userWithResources();
        $stand = create(Stand::class, ['name' => 'SAFKO']);
        $stand->bikes()->save(make(Bike::class, ['bike_num'=>1]));
        $stand->bikes()->save(make(Bike::class, ['bike_num'=>2]));
        $stand->bikes()->save(make(Bike::class, ['bike_num'=>3]));

        // Assert 1
        Notification::fake();
        $this->get($this->buildSmsUrl($user, 'RETURN 2 SAFKO'));
        Notification::assertSentTo($user, NoBikesRented::class);

        // Act
        $this->get($this->buildSmsUrl($otherUser, 'RENT 1'));
        $this->get($this->buildSmsUrl($user, 'RENT 2'));

        // Assert 2
        $this->get($this->buildSmsUrl($user, 'RETURN 1 SAFKO'));
        Notification::assertSentTo($user, BikeToReturnNotRentedByMe::class);

        $this->get($this->buildSmsUrl($user, 'RETURN 3 SAFKO'));
        Notification::assertSentTo($user, BikeToReturnNotRentedByMe::class);
    }

    /** @test */
    public function return_command_ok()
    {
        $user = userWithResources();
        create(Stand::class, ['name' => 'SAFKO'])->bikes()->save(make(Bike::class, ['bike_num'=>1]));

        $this->get($this->buildSmsUrl($user, 'RENT 1'));

        Notification::fake();
        $this->get($this->buildSmsUrl($user, 'RETURN 1 SAFKO'));

        Notification::assertSentTo($user, BikeReturnedSuccess::class);
    }

    /** @test */
    public function where_command_non_existing_bike_number()
    {
        $user = create(User::class);
        create(Stand::class)->bikes()->save(make(Bike::class, ['bike_num'=>1]));

        Notification::fake();
        $this->get($this->buildSmsUrl($user, 'WHERE 2'));

        Notification::assertSentTo($user, BikeDoesNotExist::class);
    }

    /** @test */
    public function where_command_ok()
    {
        $user = create(User::class);
        create(Stand::class)->bikes()->save(make(Bike::class, ['bike_num'=>1]));

        Notification::fake();
        $this->get($this->buildSmsUrl($user, 'WHERE 1'));
        Notification::assertSentTo($user, WhereIsBike::class);

        $this->get($this->buildSmsUrl($user, 'WHO 1'));
        Notification::assertSentTo($user, WhereIsBike::class);
    }

    /** @test */
    public function info_command_non_existing_stand_name()
    {
        $stand = create(Stand::class, ['name' => 'SAFKO']);
        $user = create(User::class);

        Notification::fake();
        $this->get($this->buildSmsUrl($user, 'INFO NONEXISTING'));

        Notification::assertSentTo($user, StandDoesNotExist::class);
    }

    /** @test */
    public function info_command_ok()
    {
        $stand = create(Stand::class);
        $user = create(User::class);

        Notification::fake();
        $this->get($this->buildSmsUrl($user, 'INFO ' . $stand->name));

        Notification::assertSentTo($user, StandInfo::class);
    }

    /** @test */
    public function note_command_non_existing_bike_and_stand()
    {
        $user = create(User::class);

        Notification::fake();

        $this->get($this->buildSmsUrl($user, 'NOTE 1 xyz'));
        Notification::assertSentTo($user, BikeDoesNotExist::class);

        $this->get($this->buildSmsUrl($user, 'NOTE ABCD xyz'));
        Notification::assertSentTo($user, StandDoesNotExist::class);
    }

    /** @test */
    public function note_command_missing_note_text()
    {
        $user = create(User::class);

        Notification::fake();

        $this->get($this->buildSmsUrl($user, 'NOTE 1'));
        Notification::assertSentTo($user, NoteTextMissing::class);
    }

    /** @test */
    public function note_command_note_to_bike_ok()
    {
        $user = create(User::class);
        create(Stand::class)->bikes()->save(make(Bike::class, ['bike_num'=>1]));

        Notification::fake();
        $this->get($this->buildSmsUrl($user, 'NOTE 1 note text is here'));

        Notification::assertSentTo($user, NoteForBikeSaved::class);
    }

    /** @test */
    public function note_command_note_to_stand_ok()
    {
        $user = create(User::class);
        create(Stand::class, ['name' => 'SAFKO']);

        Notification::fake();
        $this->get($this->buildSmsUrl($user, 'NOTE SAFKO note text is here'));

        Notification::assertSentTo($user, NoteForStandSaved::class);
    }

    /** @test */
    public function tag_command_ok()
    {
        $user = create(User::class);
        create(Stand::class, ['name' => 'SAFKO'])->bikes()->save(make(Bike::class, ['bike_num'=>1]));

        Notification::fake();
        $this->get($this->buildSmsUrl($user, 'TAG SAFKO note text is here'));

        Notification::assertSentTo($user, TagForStandSaved::class);
    }


    /** @test */
    public function delnote_command_from_bike_not_authorized()
    {
        $user = create(User::class);
        create(Stand::class)->bikes()->save(make(Bike::class, ['bike_num'=>1]));

        Notification::fake();
        $this->get($this->buildSmsUrl($user, 'DELNOTE 1 something'));

        Notification::assertSentTo($user, Unauthorized::class);
    }

    /** @test */
    public function delnote_command_from_bike_without_notes()
    {
        $admin = userWithResources([], true);
        create(Stand::class)->bikes()->save(make(Bike::class, ['bike_num'=>1]));

        Notification::fake();
        $this->get($this->buildSmsUrl($admin, 'DELNOTE 1 something'));

        Notification::assertSentTo($admin, NoNotesDeleted::class);
    }


    /** @test */
    public function delnote_command_from_bike_ok()
    {
        $user = userWithResources();
        $admin = userWithResources([], true);
        create(Stand::class)->bikes()->save(make(Bike::class, ['bike_num'=>1]));

        Notification::fake();
        $this->get($this->buildSmsUrl($user, 'NOTE 1 note_text is here'));
        $this->get($this->buildSmsUrl($admin, 'DELNOTE 1 note_text'));
        $this->get($this->buildSmsUrl($admin, 'DELNOTE 1 note_text'));
        $this->get($this->buildSmsUrl($admin, 'DELNOTE 2 something'));

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
        $this->get($this->buildSmsUrl($user, 'DELNOTE SAFKO something'));

        Notification::assertSentTo($user, Unauthorized::class);
    }

    /** @test */
    public function delnote_command_from_stand_ok()
    {
        $admin = userWithResources([], true);
        create(Stand::class, ['name' => 'SAFKO']);

        Notification::fake();
        $this->get($this->buildSmsUrl($admin, 'NOTE SAFKO poznamka text is here'));
        $this->get($this->buildSmsUrl($admin, 'DELNOTE SAFKO poznamka'));
        $this->get($this->buildSmsUrl($admin, 'DELNOTE SAFKO poznamka'));
        $this->get($this->buildSmsUrl($admin, 'DELNOTE SAFKO2 something'));

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
        $this->get($this->buildSmsUrl($user, 'TAG SAFKO note text is here'));
        Notification::assertSentTo($user, TagForStandSaved::class);

        $this->get($this->buildSmsUrl($admin, 'UNTAG SAFKO text'));
        Notification::assertSentTo($admin, AllNotesDeleted::class);

        $this->get($this->buildSmsUrl($admin, 'UNTAG SAFKO text'));
        Notification::assertSentTo($admin, NoBikesUntagged::class);

        $this->get($this->buildSmsUrl($admin, 'UNTAG SAFKO2'));
        Notification::assertSentTo($admin, NoBikesUntagged::class);

        // Tag MANDERLAK
        $this->get($this->buildSmsUrl($admin, 'TAG MANDERLAK now something completely different'));
        Notification::assertSentTo($admin, TagForStandSaved::class);

        $this->get($this->buildSmsUrl($admin, 'UNTAG MANDERLAK'));
        Notification::assertSentTo($admin, AllNotesDeleted::class);

        $this->get($this->buildSmsUrl($admin, 'UNTAG MANDERLAK'));
        Notification::assertSentTo($admin, NoBikesUntagged::class);
    }

    private function buildSmsUrl($user, $text)
    {
        $getParams = $this->smsRequest->buildGetQuery($text, $user->phone_number,1,1);
        return self::URL_PREFIX . '?' . $getParams;
    }
}
