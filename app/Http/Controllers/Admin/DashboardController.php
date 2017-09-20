<?php
namespace BikeShare\Http\Controllers\Admin;

use BikeShare\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        toastr()->info('success', 'ok');

        return view('admin.dashboard');
    }
}
