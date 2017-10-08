<?php

namespace BikeShare\Http\Controllers\Api\v1\Rents;

use BikeShare\Domain\Bike\BikesRepository;
use BikeShare\Domain\Rent\MethodType;
use BikeShare\Domain\Rent\RentsRepository;
use BikeShare\Domain\Rent\RentStatus;
use BikeShare\Domain\Rent\RentTransformer;
use BikeShare\Domain\Rent\Requests\CreateRentRequest;
use BikeShare\Domain\Rent\ReturnMethod;
use BikeShare\Domain\Stand\StandsRepository;
use BikeShare\Http\Controllers\Api\v1\Controller;
use BikeShare\Http\Services\Rents\Exceptions\BikeNotFreeException;
use BikeShare\Http\Services\Rents\Exceptions\BikeNotOnTopException;
use BikeShare\Http\Services\Rents\Exceptions\LowCreditException;
use BikeShare\Http\Services\Rents\Exceptions\MaxNumberOfRentsException;
use BikeShare\Http\Services\Rents\Exceptions\NotRentableStandException;
use BikeShare\Http\Services\Rents\Exceptions\RentException;
use BikeShare\Http\Services\Rents\RentService;
use Illuminate\Http\Request;

class RentsController extends Controller
{
    protected $rentRepo;

    public function __construct(RentsRepository $repository)
    {
        $this->rentRepo = $repository;
        $this->rentService = app(RentService::class, ['methodType' => MethodType::APP]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $rents = $this->rentRepo->all();

        return $this->response->collection($rents, new RentTransformer());
    }

    public function active()
    {
        $rents = $this->rentRepo->findWhere(['status' => RentStatus::OPEN]);

        return $this->response->collection($rents, new RentTransformer());
    }

    public function history()
    {
        $rents = $this->rentRepo->findWhere(['status' => RentStatus::CLOSE]);

        return $this->response->collection($rents, new RentTransformer());
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param CreateRentRequest|Request $request
     *
     * @param RentService $rentService
     *
     * @return \Illuminate\Http\Response
     */
    public function store(CreateRentRequest $request, RentService $rentService)
    {
        if (! $bike = app(BikesRepository::class)->findByUuid($request->get('bike'))) {
            $this->response->errorNotFound('Bike not found!');
        }

        $rent = null;
        try {
            // TODO check too many, i don't understand yet
            $rent = $rentService->rentBike($this->user, $bike);
        }
        catch (LowCreditException $e)
        {
            $this->response->errorBadRequest('You do not have required credit for rent bike!');
        }
        catch (BikeNotFreeException $e)
        {
            $this->response->errorNotFound('Bike is not free!');
        }
        catch (BikeNotOnTopException $e)
        {
            $this->response->errorBadRequest('Bike is not on the top!');
        }
        catch (MaxNumberOfRentsException $e)
        {
            $this->response->errorBadRequest('You reached the maximum number of rents!');
        }
        catch (NotRentableStandException $e)
        {
            $this->response->errorBadRequest($e->getMessage());
        }
        catch (RentException $e)
        {
            throw $e;
        }
        return $this->response->item($rent, new RentTransformer());
    }

    /**
     * Display the specified resource.
     *
     * @param  int $uuid
     * @return \Illuminate\Http\Response
     */
    public function show($uuid)
    {
        if (! $rent = $this->rentRepo->findByUuid($uuid)) {
            return $this->response->errorNotFound('Rent not found');
        }

        return $this->response->item($rent, new RentTransformer);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $uuid
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $uuid)
    {
        $rent = $this->rentRepo->findByUuid($uuid);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $uuid
     * @return \Illuminate\Http\Response
     */
    public function destroy($uuid)
    {
        $rent = $this->rentRepo->findByUuid($uuid);
    }

    public function close(Request $request, $uuid)
    {
        if (! $rent = $this->rentRepo->findByUuid($uuid)) {
            $this->response->errorNotFound('Rent not found!');
        }

        if (! $stand = app(StandsRepository::class)->findByUuid($request->get('stand'))) {
            $this->response->errorNotFound('Stand not found!');
        }

        $rent = $this->rentService->closeRent($rent, $stand);
        // TODO catch exceptions

        if ($request->filled('note')) {
            $this->rentService->addNoteToBike($rent->bike, $rent->user, $request->get('note'));
        }

        return $this->response->item($rent, new RentTransformer());
    }
}
