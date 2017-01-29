<?php
namespace BikeShare\Http\ViewComposers;

use BikeShare\Domain\Rent\RentStatus;
use BikeShare\Domain\Stand\StandsRepository;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;

class AppComposer
{

    protected $users;
    protected $stands;
    protected $date;
    protected $version;
    protected $roles;
    protected $notifications;


    /**
     * Create a new profile composer.
     *
     * @param StandsRepository $stands
     *
     * @internal param UserRepository $users
     */
    public function __construct(StandsRepository $stands)
    {
        // Dependencies automatically resolved by service container...
        $this->stands = $stands->all();
        $this->user = auth()->user();
        $this->date = Carbon::now()->toDateString();
        $this->version = config('app.version');
        $this->roles = $this->user->roles()->get();
        $this->notifications = $this->user->unreadNotifications;
    }

    /**
     * Bind data to the view.
     *
     * @param  View  $view
     * @return void
     */
    public function compose(View $view)
    {
        $view->with('stands', $this->stands);
        $view->with('activeRents', $this->user->rents()->where('rents.status', RentStatus::OPEN)->get());
        $view->with('date', $this->date);
        $view->with('version', $this->version);
        $view->with('roles', $this->roles);
        $view->with('currentUser', $this->user);
        $view->with('notifications', $this->notifications);
    }
}
