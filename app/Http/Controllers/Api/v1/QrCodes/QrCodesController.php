<?php

namespace BikeShare\Http\Controllers\Api\v1\QrCodes;

use BikeShare\Domain\Bike\BikesRepository;
use BikeShare\Domain\Rent\MethodType;
use BikeShare\Domain\Rent\RentTransformer;
use BikeShare\Domain\Stand\StandsRepository;
use BikeShare\Http\Controllers\Api\v1\Controller;
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
        StandsRepository $standsRepository,
        AppConfig $appConfig
    ) {
        $this->bikesRepository = $bikesRepository;
        $this->standsRepository = $standsRepository;
        $this->appConfig = $appConfig;
        $this->rentService = new RentService($appConfig, MethodType::QR_CODE);
    }


    protected function rentBike($bikeNo)
    {
        $bike = $this->bikesRepository->getBikeOrFail($bikeNo);
        try {
            $rent = $this->rentService->rentBike($this->user, $bike);
        } catch (LowCreditException $e) {
            $text = 'Please, recharge your credit: '
                . $e->userCredit
                . $this->appConfig->getCreditCurrency()
                . '. Credit required: '
                . $e->requiredCredit
                . $this->appConfig->getCreditCurrency() . '.';
            $this->response->errorBadRequest($text);
        } catch (BikeNotFreeException $e) {
            $this->response->errorBadRequest('Bike ' . $bike->bike_num . ' is already rented.');
        } catch (MaxNumberOfRentsException $e) {
            $this->response->errorBadRequest('You can only rent ' . $e->userLimit . ' bike at once.');
        } catch (BikeNotOnTopException $e) {
            $this->response->errorBadRequest('Bike ' . $bike->bike_num . ' is not rentable now, you have to rent bike ' . $e->topBike->bike_num . ' from this stand.');
        } catch (NotRentableStandException $e) {
            $this->response->errorBadRequest($e->getMessage());
        } catch (RentException $e) {
            throw $e; // unknown type, rethrow
        }

        return $this->response->item($rent, new RentTransformer());
    }


    public function returnBike($bikeNo, $standName)
    {
        if ($this->bikesRepository->bikesRentedByUserCount($this->user) === 0) {
            $this->response->error('You have no rented bikes currently.', 400);
        }

        $bike = $this->bikesRepository->getBikeOrFail($bikeNo);
        $stand = $this->standsRepository->getStandOrFail($standName);
        try {
            $rent = $this->rentService->returnBike($this->user, $bike, $stand);
        } catch (BikeNotRentedException | BikeRentedByOtherUserException $e) {
            $this->response->error('You do not have bike' . $bike->bike_num . ' rented.', 400);
        } catch (NotReturnableStandException $e) {
            $this->response->errorBadRequest($e->getMessage());
        } catch (ReturnException $e) {
            throw $e; // unknown type, rethrow
        }

        return $this->response->item($rent, new RentTransformer());
    }
}
