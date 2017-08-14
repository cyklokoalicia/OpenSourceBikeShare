<?php

namespace BikeShare\Http\Controllers\Stands;

use BikeShare\Domain\Bike\BikeStatus;
use BikeShare\Domain\Stand\StandsRepository;
use BikeShare\Http\Controllers\Controller;
use Illuminate\Http\Request;

class StandsApiController extends Controller
{

    protected $standRepo;

    protected $fractal;


    public function __construct(StandsRepository $repository)
    {
        parent::__construct();
        $this->standRepo = $repository;
    }


    public function index()
    {
        $stands = app(StandsRepository::class)->with(['bikes'])->all();

        return response()->json(['stands' => $stands]);
    }


    /**
     * Display the specified resource.
     *
     * @param $slug
     *
     * @return \Illuminate\Http\Response
     *
     */
    public function show($slug)
    {
        $stand = $this->standRepo->findBy('name', $slug);
        $bikes = $stand->bikes()->with([
            'rents' => function ($query) {
                $query->latest()->with('user')->first();
            },
        ])->where('status', BikeStatus::FREE)->get();

        return response()->json([
            'stand' => $stand,
            'bikes' => $bikes,
        ]);
    }
}
