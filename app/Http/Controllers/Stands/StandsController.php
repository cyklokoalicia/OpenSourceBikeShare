<?php

namespace BikeShare\Http\Controllers\Stands;

use BikeShare\Domain\Bike\BikeStatus;
use BikeShare\Domain\Stand\Requests\CreateStandRequest;
use BikeShare\Domain\Stand\Requests\UpdateStandRequest;
use BikeShare\Domain\Stand\StandService;
use BikeShare\Domain\Stand\StandsRepository;
use BikeShare\Domain\Stand\StandStatus;
use BikeShare\Http\Controllers\Controller;
use Illuminate\Http\Request;
use BikeShare\Http\Requests;

class StandsController extends Controller
{

    protected $standRepo;

    protected $fractal;


    public function __construct(StandsRepository $repository)
    {
        parent::__construct();
        $this->standRepo = $repository;
    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $stands = $this->standRepo->with(['bikes'])->all();

        return view('stands.index', [
            'stands' => $stands,
        ]);
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('stands.create', [
            'statuses' => with(new StandStatus())->toArray(),
        ]);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param CreateStandRequest|Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(CreateStandRequest $request)
    {
        $this->standRepo->create($request->all());

        toastr()->success('Stand successfully created');

        return redirect()->route('app.stands.index');
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
        $stand = $this->standRepo->findByUuid($uuid);
        $bikes = $stand->bikes()->with([
            'rents' => function ($query) {
                $query->latest()->with('user')->first();
            },
        ])->where('status', BikeStatus::FREE)->get();

        return view('stands.show', [
            'stand' => $stand,
            'bikes' => $bikes,
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
        $stand = $this->standRepo->findByUuid($uuid);

        // FORMAT AND SEND STATUSESS TO FE

        return view('stands.edit', [
            'stand' => $stand,
            'statuses' => with(new StandStatus())->toArray(),
        ]);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param UpdateStandRequest|Request $request
     * @param  int                       $uuid
     *
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateStandRequest $request, $uuid)
    {
        $this->standRepo->update($request->all(), $uuid, 'uuid');
        toastr()->success('Stand successfully updated');

        return redirect()->route('app.stands.index');
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
        $stand = $this->standRepo->findByUuid($uuid);
    }
}
