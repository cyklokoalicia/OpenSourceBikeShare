<?php
namespace BikeShare\Http\Controllers;

class DashboardController extends Controller
{
    public function index()
    {
        toastr()->info('success', 'ok');

        return view('dashboard');
    }
}
