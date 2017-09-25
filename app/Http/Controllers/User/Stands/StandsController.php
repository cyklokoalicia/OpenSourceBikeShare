<?php

namespace BikeShare\Http\Controllers\User\Stands;

use BikeShare\Domain\Stand\Stand;
use BikeShare\Domain\Stand\StandsRepository;
use BikeShare\Domain\Stand\StandTransformer;
use BikeShare\Http\Controllers\Controller;
use Illuminate\Http\Request;

class StandsController extends Controller
{

    protected $standRepo;


    public function __construct(StandsRepository $standsRepository)
    {
        $this->standRepo = $standsRepository;
    }


    public function index()
    {
        $stands = $this->standRepo->with(['bikes', 'media'])->all();

        return view('')->with([
            'stands' => $stands
        ]);
    }


    public function show($uuid)
    {
        $stand = $this->standRepo->findByUuid($uuid);

        return view('')->with([
            'stand' => $stand->load('media', 'bikes')
        ]);
    }
}
