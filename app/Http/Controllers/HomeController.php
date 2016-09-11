<?php

namespace BikeShare\Http\Controllers;

use BikeShare\Domain\Rent\RentsRepository;
use BikeShare\Domain\Stand\StandsRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HomeController extends Controller
{

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {

    }


    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // TODO where is bike?, where is not service stand
        $stands = app(StandsRepository::class)->all();
        $rents = auth()->user()->rents()->get();

        return view('home')->with([
            'date'    => Carbon::now()->toDateString(),
            'version' => config('app.version'),
            'stands'  => $stands,
            'activeRents'  => $rents,
        ]);
    }
}
