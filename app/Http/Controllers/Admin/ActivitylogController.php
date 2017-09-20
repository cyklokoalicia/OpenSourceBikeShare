<?php
namespace BikeShare\Http\Controllers\Admin;

use BikeShare\Http\Controllers\Controller;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Contracts\Pagination\Paginator;

class ActivitylogController extends Controller
{

    public function index()
    {
        $logItems = $this->getPaginatedActivityLogItems();

        return view('admin.activityLogs.index')->with(compact('logItems'));
    }


    protected function getPaginatedActivityLogItems(): Paginator
    {
        return Activity::with('causer')
            ->orderBy('created_at', 'DESC')
            ->paginate(50);
    }
}
