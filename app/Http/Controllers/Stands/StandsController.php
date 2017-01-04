<?php
namespace BikeShare\Http\Controllers\Stands;

use BikeShare\Domain\Bike\BikeStatus;
use BikeShare\Domain\Bike\BikeTransformer;
use BikeShare\Domain\Stand\StandsRepository;
use BikeShare\Domain\Stand\StandTransformer;
use BikeShare\Http\Controllers\Controller;
use Illuminate\Http\Request;
use BikeShare\Http\Requests;
use League\Fractal;

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

        return view('stand.index', [
            'stands' => $stands
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('stand.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $uuid
     * @return \Illuminate\Http\Response
     */
    public function show($uuid)
    {
        $stand = $this->standRepo->findByUuid($uuid);
        $resource = new Fractal\Resource\Item($stand, new StandTransformer);

        $bikes = $stand->bikes()->where('status', BikeStatus::FREE)->get();
        $bikeResource = new Fractal\Resource\Collection($bikes, new BikeTransformer());

        $bikes = $this->fractal->createData($bikeResource)->toArray();
        $stand = $this->fractal->createData($resource)->toArray();

        return view('stand.show')->with([
            'stand' => $stand['data'],
            'bikes' => $bikes['data'],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $uuid
     * @return \Illuminate\Http\Response
     */
    public function edit($uuid)
    {
        $stand = $this->standRepo->findByUuid($uuid);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $uuid
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $uuid)
    {
        $stand = $this->standRepo->findByUuid($uuid);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $uuid
     * @return \Illuminate\Http\Response
     */
    public function destroy($uuid)
    {
        $stand = $this->standRepo->findByUuid($uuid);
    }
}
