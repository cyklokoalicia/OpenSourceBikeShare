<?php

namespace BikeShare\Http\Controllers\Admin\Bikes;

use BikeShare\Domain\Bike\BikeStatus;
use BikeShare\Domain\Bike\Requests\CreateBikeRequest;
use BikeShare\Domain\Rent\Rent;
use BikeShare\Domain\Rent\RentsRepository;
use BikeShare\Domain\Rent\RentStatus;
use BikeShare\Domain\Rent\Requests\RentRequest;
use BikeShare\Domain\Bike\BikesRepository;
use BikeShare\Domain\Rent\Requests\ReturnRequest;
use BikeShare\Domain\Stand\Stand;
use BikeShare\Domain\Stand\StandsRepository;
use BikeShare\Domain\User\User;
use BikeShare\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use BikeShare\Http\Requests;
use ReflectionClass;

class BikesController extends Controller
{

    protected $bikeRepo;


    public function __construct(BikesRepository $bikesRepository)
    {
        $this->bikeRepo = $bikesRepository;
    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $bikes = $this->bikeRepo->with(['stand', 'user'])->all();

        return view('admin.bikes.index', [
            'bikes' => $bikes,
        ]);
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $status = (new ReflectionClass(BikeStatus::class))->getConstants();
        $users = User::all();
        $stands = Stand::all();

        return view('admin.bikes.create', [
            'status' => $status,
            'users' => $users,
            'stands' => $stands,
        ]);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param CreateBikeRequest|Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(CreateBikeRequest $request)
    {
        $this->bikeRepo->create($request->all());

        toastr()->success('Bike successfully created');

        return redirect()->route('admin.bikes.index');
    }


    /**
     * Display the specified resource.
     *
     * @param  int $uuid
     *
     * @return \Illuminate\Http\Response
     */
    public function show($uuid)
    {
        $bike = $this->bikeRepo->findByUuid($uuid);

        return view('admin.bikes.show', [
            'bike' => $bike,
        ]);
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $uuid
     *
     * @return \Illuminate\Http\Response
     */
    public function edit($uuid)
    {
        $status = (new ReflectionClass(BikeStatus::class))->getConstants();
        $users = User::all();
        $stands = Stand::all();

        if (!$bike = $this->bikeRepo->findByUuid($uuid)) {
            toastr()->error('Bike Not found!');
        }

        return view('admin.bikes.edit', [
            'status' => $status,
            'bike' => $bike,
            'users' => $users,
            'stands' => $stands,
        ]);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $uuid
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $uuid)
    {
        $this->bikeRepo->update($request->all(), $uuid, 'uuid');
        toastr()->success('Bike successfully updated');

        return redirect()->route('admin.bikes.index');
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int $uuid
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy($uuid)
    {
        //
    }


    public function rent(RentRequest $request, $uuid)
    {
        // TODO limits, credits
        $stand = app(StandsRepository::class)->findByUuid($request->get('stand'));
        $bike = $this->bikeRepo->findByUuid($uuid);
        $oldCode = $bike->current_code;
        $newCode = $this->bikeRepo->generateCode();

        $bike->status = BikeStatus::OCCUPIED;
        $bike->current_code = $newCode;
        $bike->stand()->dissociate($bike->stand);
        $bike->user()->associate(auth()->user());

        $bike->save();

        $rent = new Rent();
        $rent->status = RentStatus::OPEN;
        $rent->user()->associate(auth()->user());
        $rent->bike()->associate($bike);
        $rent->standFrom()->associate($stand);
        $rent->started_at = Carbon::now();
        $rent->old_code = $oldCode;
        $rent->new_code = $newCode;

        $rent->save();

        return redirect()->route('admin.rents.index');
    }


    public function returnBike(ReturnRequest $request, $uuid)
    {
        // TODO only if i have rented this bike
        $stand = app(StandsRepository::class)->findByUuid($request->get('stand'));

        $bike = $this->bikeRepo->findByUuid($uuid);
        $bike->status = BikeStatus::FREE;
        $bike->stand()->associate($stand);
        $bike->save();

        $rent = $bike->rents()->where('rents.status', RentStatus::OPEN);
        $rent->standTo()->associate($stand);
        $rent->ended_at = Carbon::now();
        $rent->status = RentStatus::CLOSE;
        $rent->save();

    }
}
