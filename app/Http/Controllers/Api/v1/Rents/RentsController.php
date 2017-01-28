<?php

namespace BikeShare\Http\Controllers\Api\v1\Rents;

use BikeShare\Domain\Bike\BikesRepository;
use BikeShare\Domain\Bike\BikeStatus;
use BikeShare\Domain\Bike\Events\BikeWasReturned;
use BikeShare\Domain\Rent\Events\RentWasClosed;
use BikeShare\Domain\Rent\RentsRepository;
use BikeShare\Domain\Rent\RentStatus;
use BikeShare\Domain\Rent\RentTransformer;
use BikeShare\Domain\Rent\Requests\CreateRentRequest;
use BikeShare\Domain\Stand\StandsRepository;
use BikeShare\Domain\User\UsersRepository;
use BikeShare\Http\Controllers\Api\v1\Controller;
use BikeShare\Http\Services\RentService;
use BikeShare\Notifications\NoteCreated;
use Illuminate\Http\Request;
use Notification;

class RentsController extends Controller
{
    protected $rentRepo;

    public function __construct(RentsRepository $repository)
    {
        $this->rentRepo = $repository;
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
        if (!$bike = app(BikesRepository::class)->findByUuid($request->get('bike'))) {
            return $this->response->errorNotFound('Bike not found!');
        }

        if ($bike->status != BikeStatus::FREE) {
            return $this->response->errorNotFound('Bike is not free!');
        }

        $currentRents = $this->user->bikes()->get()->count();
        if ($currentRents >= $this->user->limit) {
            return $this->response->errorBadRequest('You reached the maximum number of rents!');
        }

        // TODO check too many, i don't understand yet

        if (app('AppConfig')->isStackBikeEnabled()) {
            if (!$rentService->checkTopOfStack($bike)) {
                return $this->response->errorBadRequest('Bike is not on the top!');
            }
        }

        if (app('AppConfig')->isCreditEnabled()) {
            $requiredCredit = app('AppConfig')->getRequiredCredit();
            if ($this->user->credit < $requiredCredit) {
                return $this->response->errorBadRequest('You do not have required credit for rent bike!');
            }
        }

        $rentServiceObj = $rentService->rentBike($this->user, $bike)->createRentLog();

        dd($rentServiceObj);
        //$rent = app(RentService::class)->rentBike($this->user, $bike);

        //event(new RentWasCreated($rent));
        //event(new BikeWasRented($bike, $rent->new_code, $this->user));

        return $this->response->item($rentServiceObj->rent, new RentTransformer());
    }

    /**
     * Display the specified resource.
     *
     * @param  int $uuid
     * @return \Illuminate\Http\Response
     */
    public function show($uuid)
    {
        if (!$rent = $this->rentRepo->findByUuid($uuid)) {
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

    public function close(Request $request, $uuid, RentService $rentService)
    {
        if (!$rent = $this->rentRepo->findByUuid($uuid)) {
            return $this->response->errorNotFound('Rent not found!');
        }

        if ($rent->status != RentStatus::OPEN) {
            $this->response->errorBadRequest('Rent is not active!');
        }

        $userBikes = $this->user->bikes()->get();
        if (!$userBikes || !$userBikes->contains($rent->bike)) {
            $this->response->errorBadRequest('You do not have rent this bike!');
        }

        if (!$stand = app(StandsRepository::class)->findByUuid($request->get('stand'))) {
            return $this->response->errorNotFound('Stand not found!');
        }

        $rentServiceObj = $rentService->returnBike($this->user, $stand, $rent)->closeRentLog()->updateCredit();

        if ($request->has('note')) {
            $rentServiceObj = $rentService->addNote($rent->bike, $request->get('note'));
            $users = app(UsersRepository::class)->getUsersWithRole('admin')->get();
            Notification::send($users, new NoteCreated($rentServiceObj->note));
        }

        event(new RentWasClosed($rentServiceObj->rent));
        event(new BikeWasReturned($rentServiceObj->bike, $stand));

        return $this->response->item($rentServiceObj->rent, new RentTransformer());
    }
}
