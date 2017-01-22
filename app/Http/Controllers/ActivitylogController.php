<?php
namespace BikeShare\Http\Controllers;

use Spatie\Activitylog\Models\Activity;
use Illuminate\Contracts\Pagination\Paginator;

class ActivitylogController extends Controller
{

    public function index()
    {
        $logItems = $this->getPaginatedActivityLogItems();

        return view('activityLogs.index')->with(compact('logItems'));
    }


    protected function getPaginatedActivityLogItems(): Paginator
    {
        return Activity::with('causer')
            ->orderBy('created_at', 'DESC')
            ->paginate(50);
    }
}
