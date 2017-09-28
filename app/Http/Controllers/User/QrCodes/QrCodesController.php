<?php

namespace BikeShare\Http\Controllers\User\QrCodes;

use BikeShare\Domain\Bike\BikesRepository;
use BikeShare\Domain\Rent\MethodType;
use BikeShare\Domain\Rent\ReturnMethod;
use BikeShare\Domain\Stand\StandsRepository;
use BikeShare\Http\Controllers\Controller;
use BikeShare\Http\Services\AppConfig;
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

class QrCodesController extends Controller
{

    /**
     * @var BikesRepository
     */
    private $bikesRepository;

    /**
     * @var RentService
     */
    private $rentService;

    /**
     * @var StandsRepository
     */
    private $standsRepository;

    /**
     * @var AppConfig
     */
    private $appConfig;


    public function __construct(
        BikesRepository $bikesRepository,
        RentService $rentService,
        StandsRepository $standsRepository,
        AppConfig $appConfig
    ) {
        $this->bikesRepository = $bikesRepository;
        $this->rentService = $rentService;
        $this->standsRepository = $standsRepository;
        $this->appConfig = $appConfig;
    }


    protected function rentBike($bikeNo)
    {
        $bike = $this->bikesRepository->getBikeOrFail($bikeNo);
        try {
            $rent = $this->rentService->rentBike(auth()->user(), $bike);
            toastr()->success('Bike was successfully rented');
        } catch (LowCreditException $e) {
            $text = 'Please, recharge your credit: '
                . $e->userCredit
                . $this->appConfig->getCreditCurrency()
                . '. Credit required: '
                . $e->requiredCredit
                . $this->appConfig->getCreditCurrency() . '.';
            toastr()->error($text);
        } catch (BikeNotFreeException $e) {
            toastr()->error('Bike ' . $bike->bike_num . ' is already rented.');
        } catch (MaxNumberOfRentsException $e) {
            toastr()->error('You can only rent ' . $e->userLimit . ' bike at once.');
        } catch (BikeNotOnTopException $e) {
            toastr()->error('Bike ' . $bike->bike_num . ' is not rentable now, you have to rent bike ' . $e->topBike->bike_num . ' from this stand.');
        } catch (NotRentableStandException $e) {
            toastr()->error($e->getMessage());
        } catch (RentException $e) {
            throw $e; // unknown type, rethrow
        }

        return redirect()->route('app.rents.show', $rent->uuid);
    }


    public function beforeReturn($standName)
    {
        $activeRents = $this->bikesRepository->bikesRentedByUserCount(auth()->user());
        if ($activeRents === 0) {
            toastr()->error('You have no rented bikes currently.');
        } elseif ($activeRents === 1) {
            $bikes = $this->bikesRepository->bikesRentedByUser(auth()->user());

            return $this->returnBike($bikes->first(), $standName);
        } else {
            $bikes = $this->bikesRepository->bikesRentedByUser(auth()->user());

            // TODO view with select active rented bikes
            return view('')->with([
                'bikes' => $bikes,
            ]);
        }
    }


    public function returnBike($bikeNo, $standName)
    {
        if ($this->bikesRepository->bikesRentedByUserCount(auth()->user()) === 0) {
            toastr()->error('You have no rented bikes currently.');
        }

        $bike = $this->bikesRepository->getBikeOrFail($bikeNo);
        $stand = $this->standsRepository->getStandOrFail($standName);
        try {
            $rent = $this->rentService->returnBike(auth()->user(), $bike, $stand);
            toastr()->success('You return action was successful');
        } catch (BikeNotRentedException | BikeRentedByOtherUserException $e) {
            toastr()->error('You do not have bike' . $bike->bike_num . ' rented.');
        } catch (NotReturnableStandException $e) {
            toastr()->error($e->getMessage());
        } catch (ReturnException $e) {
            throw $e; // unknown type, rethrow
        }

        return view('')->with([
            'rent' => $rent,
        ]);
    }
}
