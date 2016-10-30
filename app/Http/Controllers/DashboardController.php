<?php
namespace BikeShare\Http\Controllers;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard')->with([

        ]);
    }
}
