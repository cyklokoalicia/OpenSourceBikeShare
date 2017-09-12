<?php
namespace BikeShare\Http\Services\Rents;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Bike\BikePermissions;
use BikeShare\Domain\Bike\BikesRepository;
use BikeShare\Domain\Bike\BikeStatus;
use BikeShare\Domain\Bike\Events\BikeWasReturned;
use BikeShare\Domain\Note\NotesRepository;
use BikeShare\Domain\Rent\Events\RentWasClosed;
use BikeShare\Domain\Rent\Rent;
use BikeShare\Domain\Rent\RentsRepository;
use BikeShare\Domain\Rent\RentStatus;
use BikeShare\Domain\Stand\Stand;
use BikeShare\Domain\Stand\StandPermissions;
use BikeShare\Domain\User\User;
use BikeShare\Domain\User\UsersRepository;
use BikeShare\Http\Services\AppConfig;
use BikeShare\Http\Services\Rents\Exceptions\BikeNotFreeException;
use BikeShare\Http\Services\Rents\Exceptions\BikeNotOnTopException;
use BikeShare\Http\Services\Rents\Exceptions\BikeNotRentedException;
use BikeShare\Http\Services\Rents\Exceptions\BikeRentedByOtherUserException;
use BikeShare\Http\Services\Rents\Exceptions\LowCreditException;
use BikeShare\Http\Services\Rents\Exceptions\MaxNumberOfRentsException;
use BikeShare\Notifications\Admin\AllNotesDeleted;
use BikeShare\Notifications\Admin\BikeNoteAdded;
use BikeShare\Notifications\Admin\NotesDeleted;
use BikeShare\Notifications\Admin\StandNoteAdded;
use Carbon\Carbon;
use Exception;
use Gate;
use Notification;

class RentService
{
    /**
     * @var AppConfig
     */
    private $appConfig;

    public function __construct(AppConfig $appConfig)
    {
        $this->appConfig = $appConfig;
    }

    /**
     * @param User $user
     * @param Bike $bike
     * @return Rent
     * @throws LowCreditException
     * @throws BikeNotFreeException
     * @throws BikeNotOnTopException
     * @throws MaxNumberOfRentsException
     * @throws Exception
     */
    public function rentBike(User $user, Bike $bike)
    {
        if ($this->appConfig->isCreditEnabled()){
            $requiredCredit = $this->appConfig->getRequiredCredit();

            if ($user->credit < $requiredCredit){
                throw new LowCreditException($user->credit, $requiredCredit);
            }
        }

        // TODO checkTooMany

        if ($bike->status != BikeStatus::FREE) {
            if (!$bike->user){
                throw new Exception("Bike not free but no owner, bike_id = {$bike->user_id}");
            }
            throw new BikeNotFreeException();
        }

        $currentRents = $user->bikes()->get()->count();
        if ($currentRents >= $user->limit) {
            throw new MaxNumberOfRentsException($user->limit, $currentRents);
        }

        if ($this->appConfig->isStackBikeEnabled() && !$this->checkTopOfStack($bike)){
            throw new BikeNotOnTopException($bike->stand->getTopBike());
        }

        if ($this->appConfig->isStackWatchEnabled() && !$this->checkTopOfStack($bike)){
            // TODO notifyAdmin
        }

        $oldCode = $bike->current_code;
        $standFrom = $bike->stand;

        $this->rentBikeInternal($bike, $user);
        $rent = $this->createRentLog($user, $bike, $standFrom, $oldCode, $bike->current_code);
        // TODO enable events
//        event(new RentWasCreated($rent));
//        event(new BikeWasRented($bike, $rent->new_code, $user));
        return $rent;
    }

    private function rentBikeInternal(Bike $bike, User $user)
    {
        $bike->status = BikeStatus::OCCUPIED;
        $bike->current_code = Bike::generateBikeCode();
        $bike->stack_position = 0;
        $bike->stand()->dissociate();
        $bike->user()->associate($user);
        $bike->save();
    }

    /**
     * @return Rent
     */
    private function createRentLog(User $user, Bike $bike, Stand $standFrom, $oldCode, $newCode)
    {
        $rent = new Rent();
        $rent->status = RentStatus::OPEN;
        $rent->user()->associate($user);
        $rent->bike()->associate($bike);
        $rent->standFrom()->associate($standFrom);
        $rent->started_at = Carbon::now();
        $rent->old_code = $oldCode;
        $rent->new_code = $newCode;
        $rent->save();
        return $rent;
    }

    public function closeRent(Rent $rent, Stand $standToReturn)
    {
        return $this->returnBike($rent->user, $rent->bike, $standToReturn);
    }

    /**
     * @param User $user User initiating the command
     * @param Bike $bike
     * @param Stand $standTo
     * @return Rent
     * @internal param Rent|null $rent
     */
    public function returnBike(User $user, Bike $bike, Stand $standTo)
    {
        if ($bike->status !== BikeStatus::OCCUPIED){
            throw new BikeNotRentedException($bike->status);
        }

        if ($bike->user_id != $user->id){
            throw new BikeRentedByOtherUserException($bike->user);
        }

        $rent = app(RentsRepository::class)->findOpenRent($bike);

        $this->returnBikeInternal($bike, $standTo);
        $this->closeRentLogInternal($rent, $standTo);
        $this->updateCredit($rent);
        return $rent;
    }

    /**
     * Return bike to old stand, no matter if user is currently renting the bike
     * Admin only
     * @param User $user
     * @param Bike $bike
     * @return null
     */
    public function revertBikeRent(User $user, Bike $bike){
        Gate::forUser($user)->authorize(BikePermissions::REVERT);

        if ($bike->status !== BikeStatus::OCCUPIED){
            throw new BikeNotRentedException($bike->status);
        }

        $rent = app(RentsRepository::class)->findOpenRent($bike);

        $oldStand = $rent->standFrom;

        $this->returnBikeInternal($bike, $oldStand);
        $this->closeRentLogInternal($rent, $oldStand);
        return $rent;
    }

    private function returnBikeInternal(Bike $bike, Stand $standTo)
    {
        $topPosition = $standTo->getTopPosition();
        $bike->stack_position = $topPosition ? $topPosition + 1 : 0;
        $bike->status = BikeStatus::FREE;
        $bike->stand()->associate($standTo);
        $bike->user()->dissociate();
        $bike->save();

        event(new BikeWasReturned($bike, $standTo));
    }

    private function closeRentLogInternal(Rent $rent, Stand $stand)
    {
        $rent->ended_at = Carbon::now();
        $rent->duration = $rent->ended_at->diffInSeconds($rent->started_at);
        $rent->status = RentStatus::CLOSE;
        $rent->standTo()->associate($stand);
        $rent->save();
        event(new RentWasClosed($rent));
        return $rent;
    }

    private function updateCredit(Rent $rent)
    {
        $config = $this->appConfig;
        if (! $config->isCreditEnabled()) {
            return;
        }

        $realRentCredit = 0;
        $timeDiff = $rent->started_at->diffInMinutes($rent->ended_at);

        $freeTime = $config->getFreeTime();
        $rentCredit = $config->getRentCredit();
        if ($timeDiff > $freeTime) {
            $realRentCredit += $rentCredit;
        }

        // after first paid period, i.e. free_time * 2; if price_cycle enabled
        $priceCycle = $config->getPriceCycle();
        if ($priceCycle) {
            if ($timeDiff > $freeTime * 2) {
                $tempTimeDiff = $timeDiff - ($freeTime * 2);

                if ($priceCycle == 1) {             // flat price per cycle
                    $cycles = ceil($tempTimeDiff / $config->getFlatPriceCycle());
                    $realRentCredit += ($rentCredit * $cycles);
                } elseif ($priceCycle == 2) {       // double price per cycle
                    $cycles = ceil($tempTimeDiff / $config->getDoublePriceCycle());
                    $tmpCreditRent = $rentCredit;

                    for ($i = 1; $i <= $cycles; $i++) {
                        $multiplier = $i;
                        $doublePriceCycleCap = $config->getDoublePriceCycleCap();

                        if ($multiplier > $doublePriceCycleCap) {
                            $multiplier = $doublePriceCycleCap;
                        }
                        // exception for rent=1, otherwise square won't work:
                        if ($tmpCreditRent == 1) {
                            $tmpCreditRent = 2;
                        }
                        $realRentCredit += pow($tmpCreditRent, $multiplier);
                    }
                }
            }
        }

        if ($timeDiff > $config->getWatchersLongRental() * 60) {
            $realRentCredit += $config->getCreditLongRental();
        }

        $rent->credit = $realRentCredit;
        $rent->save();
        $rent->user->credit -= $realRentCredit;
        $rent->user->save();
    }

    public function addNoteToBike(Bike $bike, User $user, $noteText)
    {
        $note = $bike->notes()->create([
            'note' => $noteText,
            'user_id' => $user->id
        ]);
        $this->notifyAdmins(new BikeNoteAdded($bike, $note, $user));
        return $note;
    }

    public function addNoteToStand(Stand $stand, User $user, $noteText)
    {
        $note = $stand->notes()->create([
            'note' => $noteText,
            'user_id' => $user->id
        ]);
        $this->notifyAdmins(new StandNoteAdded($stand, $note, $user));
        return $note;
    }

    public function deleteNoteFromBike(Bike $bike, User $user, $pattern)
    {
        Gate::forUser($user)->authorize(BikePermissions::DELETE_NOTE);
        $pattern = $pattern ? "%{$pattern}%" : "%";

        $count =  $bike->notes()
            ->where('note', 'like', $pattern)->delete();

        if ($count > 0){
            Notification::send(
                app(UsersRepository::class)->getAdmins(),
                new NotesDeleted($user, $pattern, $count, $bike)
            );
        }

        return $count;
    }

    public function deleteNoteFromStand(Stand $stand, User $user, $pattern)
    {
        Gate::forUser($user)->authorize(StandPermissions::DELETE_NOTE);
        $pattern = $pattern ? "%{$pattern}%" : "%";

        $count = $stand->notes()
            ->where('note', 'like', $pattern)->delete();

        if ($count > 0){
            $this->notifyAdmins(new NotesDeleted($user, $pattern, $count, null, $stand));
        }

        return $count;
    }

    public function deleteNoteFromAllStandBikes(Stand $stand, User $user, $pattern)
    {
        Gate::forUser($user)->authorize(StandPermissions::UNTAG);
        $pattern = $pattern ? "%{$pattern}%" : "%";

        $deleted = 0;
        foreach ($stand->bikes as $b){
            $deleted += $b->notes()->where('note', 'like', $pattern)->delete();
        }
        if ($deleted > 0){
            $this->notifyAdmins(new AllNotesDeleted($user, $pattern, $deleted, $stand));
        }
        return $deleted;
    }

    public function addNoteToAllStandBikes(Stand $stand, User $user, $noteText)
    {
        $stand->bikes->each(function($bike) use ($noteText, $user){
            $bike->notes()->create([
                'note' => $noteText,
                'user_id' => $user->id
            ]);
        });

        // TODO notify Admins (email and sms if enabled)
//        $users = app(UsersRepository::class)->getUsersWithRole('admin')->get();
//        Notification::send($users, new NoteCreated($note));
    }

    private function checkTopOfStack($bike)
    {
        return $bike->stand->getTopPosition() == $bike->stack_position;
    }

    public function checkLongRent()
    {
        $rents = app(RentsRepository::class)->findWhere(['status' => RentStatus::OPEN]);

        foreach ($rents as $rent) {
            if ($rent->started_at->addHours(app(AppConfig::class)->getWatchersLongRental())->isPast()) {
                if (app(AppConfig::class)->isNotifyUser()) {
                    // TODO send notification (sms, email ?) to user about long rental
                }
            }
        }

        // TODO send notification report to admins about all long rentals
    }

    public function checkManyRents(User $user = null)
    {
        $timeToMany = app(AppConfig::class)->getTimeToMany();
        $numberToMany = app(AppConfig::class)->getNumberToMany();
        if ($user) {
            $users = collect($user);
        } else {
            $users = app(UsersRepository::class)->findWhere([
                ['limit', '!=', 0]
            ]);
        }

        foreach ($users as $user) {
            $rents = $user->rents()->where('started_at', '>', Carbon::now()->subHour($timeToMany))->get();

            if (count($rents) >= ($user->limit + $numberToMany)) {
                // TODO prepare data for report
            }
        }

        // TODO notify admins (create report) about too many rentals of users
    }

    private function notifyAdmins($notification)
    {
        Notification::send(
            app(UsersRepository::class)->getAdmins(), $notification);
    }
}
