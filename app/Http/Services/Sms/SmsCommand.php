<?php


namespace BikeShare\Http\Services\Sms;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Bike\BikePermissions;
use BikeShare\Domain\Bike\BikesRepository;
use BikeShare\Domain\Rent\RentMethod;
use BikeShare\Domain\Rent\ReturnMethod;
use BikeShare\Domain\Stand\Stand;
use BikeShare\Domain\Stand\StandsRepository;
use BikeShare\Http\Services\Rents\Exceptions\BikeNotFreeException;
use BikeShare\Http\Services\Rents\Exceptions\BikeNotOnTopException;
use BikeShare\Http\Services\Rents\Exceptions\BikeNotRentedException;
use BikeShare\Http\Services\Rents\Exceptions\BikeRentedByOtherUserException;
use BikeShare\Http\Services\Rents\Exceptions\LowCreditException;
use BikeShare\Http\Services\Rents\Exceptions\MaxNumberOfRentsException;
use BikeShare\Http\Services\Rents\Exceptions\NotRentableStandException;
use BikeShare\Http\Services\Rents\Exceptions\NotReturnableStandException;
use BikeShare\Http\Services\Rents\Exceptions\RentException;
use BikeShare\Http\Services\Rents\Exceptions\ReturnException;
use BikeShare\Http\Services\Rents\RentService;
use BikeShare\Notifications\Sms\BikeAlreadyRented;
use BikeShare\Notifications\Sms\NotRentableStand;
use BikeShare\Notifications\Sms\NotReturnableStand;
use BikeShare\Notifications\Sms\Ret\BikeForceReturnedSuccess;
use BikeShare\Notifications\Sms\Ret\BikeReturnedSuccess;
use BikeShare\Notifications\Sms\BikeToReturnNotRentedByMe;
use BikeShare\Notifications\Sms\NoBikesRented;
use BikeShare\Notifications\Sms\NoBikesUntagged;
use BikeShare\Notifications\Sms\NoNotesDeleted;
use BikeShare\Notifications\Sms\NoteForBikeSaved;
use BikeShare\Notifications\Sms\NoteForStandSaved;
use BikeShare\Notifications\Sms\NoteTextMissing;
use BikeShare\Notifications\Sms\BikeNotTopOfStack;
use BikeShare\Notifications\Sms\BikeRentedSuccess;
use BikeShare\Notifications\Sms\Credit;
use BikeShare\Notifications\Sms\Free;
use BikeShare\Notifications\Sms\Help;
use BikeShare\Notifications\Sms\InvalidArgumentsCommand;
use BikeShare\Notifications\Sms\RechargeCredit;
use BikeShare\Notifications\Sms\RentLimitExceeded;
use BikeShare\Notifications\Sms\Revert\BikeNotRented;
use BikeShare\Notifications\Sms\Revert\RentedBikeReverted;
use BikeShare\Notifications\Sms\Revert\RevertSuccess;
use BikeShare\Notifications\Sms\StandInfo;
use BikeShare\Notifications\Sms\StandListBikes;
use BikeShare\Notifications\Sms\TagForStandSaved;
use BikeShare\Notifications\Sms\UnknownCommand;
use BikeShare\Notifications\Sms\WhereIsBike;
use BikeShare\Notifications\SmsNotification;
use BikeShare\Domain\User\User;
use BikeShare\Http\Services\AppConfig;
use Illuminate\Support\Facades\Gate;

class SmsCommand
{
    /**
     * @var User
     */
    private $user;
    private $rentService;
    private $bikeRepo;
    private $standsRepo;
    private $appConfig;

    public static function by(User $user)
    {
        return new self($user);
    }

    private function __construct(User $user)
    {
        $this->user = $user;
        $this->rentService = app(RentService::class);
        $this->bikeRepo = app(BikesRepository::class);
        $this->standsRepo = app(StandsRepository::class);
        $this->appConfig = app(AppConfig::class);
    }

    public function help()
    {
        $this->user->notify(new Help($this->user, $this->appConfig));
    }

    public function unknown($command)
    {
        $this->user->notify(new UnknownCommand($command));
    }

    public function credit()
    {
        $this->user->notify(new Credit($this->appConfig, $this->user));
    }

    public function free()
    {
        $this->user->notify(new Free($this->standsRepo));
    }

    public function invalidArguments($errorMsg)
    {
        $this->user->notify(new InvalidArgumentsCommand($errorMsg));
    }

    public function rentBike(Bike $bike)
    {
        try
        {
            $rent = $this->rentService->rentBike($this->user, $bike, RentMethod::SMS);
            $this->user->notify(new BikeRentedSuccess($rent));
        }
        catch (LowCreditException $e)
        {
            $this->user->notify(new RechargeCredit($this->appConfig, $e->userCredit, $e->requiredCredit));
        }
        catch (BikeNotFreeException $e)
        {
            $this->user->notify(new BikeAlreadyRented($this->user, $bike));
        }
        catch (MaxNumberOfRentsException $e)
        {
            $this->user->notify(new RentLimitExceeded($e->userLimit, $e->currentRents));
        }
        catch (BikeNotOnTopException $e)
        {
            $this->user->notify(new BikeNotTopOfStack($bike, $e->topBike));
        }
        catch (NotRentableStandException $e)
        {
            $this->user->notify(new NotRentableStand($e->stand));
        }
        catch (RentException $e){
            throw $e; // unknown type, rethrow
        }
    }

    public function forceRentBike(Bike $bike)
    {
        $rent = $this->rentService->forceRentBike($this->user, $bike, RentMethod::SMS);
        $this->user->notify(new BikeRentedSuccess($rent));
    }



    public function returnBike(Bike $bike, Stand $stand, $noteText = null)
    {
        if ($this->bikeRepo->bikesRentedByUserCount($this->user) == 0){
            $this->user->notify(new NoBikesRented);
            return;
        }

        try {
            $rent = $this->rentService->returnBike($this->user, $bike, $stand, ReturnMethod::SMS);
            if ($noteText){
                $this->rentService->addNoteToBike($bike, $this->user, $noteText);
            }
            $this->user->notify(new BikeReturnedSuccess($this->appConfig, $rent, $noteText));
        }
        catch (BikeNotRentedException | BikeRentedByOtherUserException $e)
        {
            $this->user->notify(new BikeToReturnNotRentedByMe($this->user, $bike, $this->bikeRepo->bikesRentedByUser($this->user)));
        }
        catch (NotReturnableStandException $e)
        {
            $this->user->notify(new NotReturnableStand($stand));
        }
        catch (ReturnException $e)
        {
            throw $e; // unknown type, rethrow
        }
    }

    public function forceReturnBike(Bike $bike, Stand $stand, $noteText = null)
    {
        $this->rentService->forceReturnBike($this->user, $bike, $stand, ReturnMethod::SMS);

        if ($noteText){
            $this->rentService->addNoteToBike($bike, $this->user, $noteText);
        }

        $this->user->notify(new BikeForceReturnedSuccess($bike->bike_num, $stand->name, $bike->current_code, $noteText));
    }

    public function whereIsBike(Bike $bike)
    {
        $this->user->notify(new WhereIsBike($bike));
    }

    public function standInfo(Stand $stand)
    {
        $this->user->notify(new StandInfo($stand));
    }

    public function note($bikeOrStand, $noteText)
    {
        if (!$noteText){
            $this->user->notify(new NoteTextMissing());
            return;
        }

        $this->bikeOrStandInvoke($bikeOrStand,
            function ($bikeNum) use ($noteText){
                $this->bikeNote($bikeNum, $noteText);
            }, function ($standName) use ($noteText){
                $this->standNote($standName, $noteText);
            }
        );
    }

    public function bikeNote(Bike $bike, $noteText)
    {
        $this->rentService->addNoteToBike($bike, $this->user, $noteText);
        $this->user->notify(new NoteForBikeSaved($bike));
    }

    public function standNote(Stand $stand, $noteText)
    {
        $this->rentService->addNoteToStand($stand, $this->user, $noteText);
        $this->user->notify(new NoteForStandSaved($stand));
    }

    public function tag(Stand $stand, $note)
    {
        if (!$note){
            $this->user->notify(new NoteTextMissing);
            return;
        }

        $this->rentService->addNoteToAllStandBikes($stand, $this->user, $note);
        $this->user->notify(new TagForStandSaved($stand));
    }



    public function deleteNote($bikeOrStand, $notePattern)
    {
        if (!$notePattern){
            $this->user->notify(new NoteTextMissing);
            return;
        }

        $this->bikeOrStandInvoke($bikeOrStand,
            function ($bike) use ($notePattern){
                $this->bikeDeleteNote($bike, $notePattern);
            }, function ($stand) use ($notePattern){
                $this->standDeleteNote($stand, $notePattern);
            }
        );
    }
    // Helper function to call method depending on parameter type (bike/stand)

    public function bikeOrStandInvoke($bikeOrStand, callable $callableBike, callable $callableStand)
    {
        if (preg_match("/^[0-9]*$/", $bikeOrStand))
        {
            $callableBike($this->bikeRepo->getBikeOrFail($bikeOrStand));
        }
        else if (preg_match("/^[A-Z]+[0-9]*$/i", $bikeOrStand))
        {
            $callableStand($this->standsRepo->getStandOrFail($bikeOrStand));
        }
        else {
            $this->user->notify(new class($bikeOrStand) extends SmsNotification{
                private $param;
                public function __construct($param)
                {
                    $this->param = $param;
                }
                public function smsText()
                {
                    return "Error in bike number / stand name specification:" . $this->param;
                }
            });
        }
    }

    public function bikeDeleteNote(Bike $bike, $notePattern)
    {
        $deletedCount = $this->rentService->deleteNoteFromBike($bike, $this->user, $notePattern);

        // notify user only in case no notes were deleted, otherwise he/she will be notified as admin
        if ($deletedCount == 0){
            $this->user->notify(new NoNotesDeleted($this->user, $notePattern, $bike));
        }
    }

    public function standDeleteNote(Stand $stand, $notePattern)
    {
        $deletedCount = $this->rentService->deleteNoteFromStand($stand, $this->user, $notePattern);

        // notify user only in case no notes were deleted, otherwise he/she will be notified as admin
        if ($deletedCount == 0){
            $this->user->notify(new NoNotesDeleted($this->user, $notePattern, null, $stand));
        }
    }

    public function untag(Stand $stand, $notePattern)
    {
        $deletedCount = $this->rentService->deleteNoteFromAllStandBikes($stand, $this->user, $notePattern);

        // notify user only in case no notes were deleted, otherwise he/she will be notified as admin
        if ($deletedCount == 0){
            $this->user->notify(new NoBikesUntagged($notePattern, $stand));
        }
    }

    public function listBikes(Stand $stand)
    {
        $this->user->notify(new StandListBikes($stand));
    }

    public function revert(Bike $bike)
    {
        try {
            $rent = $this->rentService->revertBikeRent($this->user, $bike, ReturnMethod::SMS);
            $this->user->notify(new RevertSuccess($rent));
            $rent->user->notify(new RentedBikeReverted($bike));
        } catch (BikeNotRentedException $e) {
            $this->user->notify(new BikeNotRented($bike));
        }
    }

    public function last(Bike $bike)
    {
        Gate::forUser($this->user)->authorize(BikePermissions::LAST_RENTS);

        $this->user->notify(new LastRents($bike));
    }

}
