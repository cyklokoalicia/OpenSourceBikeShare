<?php
namespace BikeShare\Http\Controllers\Admin\Rents;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Rent\Rent;
use BikeShare\Domain\Rent\RentsRepository;
use BikeShare\Domain\Rent\RentTransformer;
use BikeShare\Domain\Stand\Stand;
use BikeShare\Domain\User\User;
use BikeShare\Http\Controllers\Controller;
use Illuminate\Http\Request;
use League\Fractal;

class RentsController extends Controller
{

    protected $rentRepo;


    public function __construct(RentsRepository $repository)
    {
        $this->rentRepo = $repository;
    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (auth()->user()->hasRole('admin')) {
            $rents = Rent::with('bike', 'standFrom', 'standTo', 'user');

            if (request()->filled('from') && request()->filled('to')) {
                $rents = $rents->where('started_at', '>=', request()->get('from'))
                    ->where(function ($query) {
                        $query->whereNull('ended_at')
                            ->orWhere('ended_at', '<=', request()->get('to'));
                    });
            }

            if (request()->filled('users')) {
                $rents = $rents->whereIn('user_id', request()->get('users'));
            }

            if (request()->filled('stands')) {
                $rents = $rents->whereIn('stand_from_id', request()->get('stands'))
                    ->orWhereIn('stand_to_id', request()->get('stands'));
            }

            if (request()->filled('bikes')) {
                $rents = $rents->whereIn('bike_id', request()->get('bikes'));
            }

            $rents = $rents->get();
        } else {
            $rents = auth()->user()->rents()->load('bike', 'standFrom')->get();
        }

        return view('admin.rents.index', [
            'rents' => $rents,
            'bikes' => Bike::all(),
            'stands' => Stand::all(),
            'users' => User::all()
        ]);
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.rents.create');
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }


    /**
     * Display the specified resource.
     *
     * @param  int $uuid
     *
     * @return \Illuminate\Http\Response
     */
    public function show($uuid)
    {
        if (! $rent = $this->rentRepo->with(['bike', 'standFrom'])->findByUuid($uuid)) {
            toastr()->warning('Rent not exists!');

            return redirect()->route('admin.rents.index');
        }

        return view('admin.rents.show')->withRent($rent);
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $uuid
     *
     * @return \Illuminate\Http\Response
     */
    public function edit($uuid)
    {
        $rent = $this->rentRepo->findByUuid($uuid);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int                      $uuid
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $uuid)
    {
        $rent = $this->rentRepo->findByUuid($uuid);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int $uuid
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy($uuid)
    {
        $rent = $this->rentRepo->findByUuid($uuid);
    }
}
