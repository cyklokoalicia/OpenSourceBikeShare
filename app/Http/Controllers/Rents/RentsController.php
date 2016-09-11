<?php
namespace BikeShare\Http\Controllers\Rents;

use BikeShare\Domain\Rent\RentsRepository;
use BikeShare\Domain\Rent\RentTransformer;
use BikeShare\Http\Controllers\Controller;
use Illuminate\Http\Request;
use League\Fractal;
use League\Fractal\Manager;

class RentsController extends Controller
{
    protected $rentRepo;
    protected $fractal;

    public function __construct(RentsRepository $repository)
    {
        $this->rentRepo = $repository;
        $this->fractal = new Manager();
        parent::__construct();
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (auth()->user()->hasRole('admin')) {
            $rents = $this->rentRepo->with(['bike', 'standFrom', 'standTo', 'user'])->all();
        } else {
            //$rents = auth()->user()->rents()->with('bike', 'standFrom', 'standTo')->get();

            $rents = auth()->user()->rents()->get();

            $include = ['bike', 'standFrom'];
            $resource = new Fractal\Resource\Collection($rents, new RentTransformer);

            if (isset($include)) {
                $this->fractal->parseIncludes(implode(",", $include));
            }

            $rents = $this->fractal->createData($resource)->toArray();
        }

//dd($rents);
        return view('rents.index', [
            'rents' => $rents
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('rents.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $uuid
     * @return \Illuminate\Http\Response
     */
    public function show($uuid)
    {
        $rent = $this->rentRepo->findByUuid($uuid);

        $include = ['bike', 'standFrom'];
        $resource = new Fractal\Resource\Item($rent, new RentTransformer);

        if (isset($include)) {
            $this->fractal->parseIncludes(implode(",", $include));
        }

        $rent = $this->fractal->createData($resource)->toArray();

        return view('rents.show')->with(['rent' => $rent]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $uuid
     * @return \Illuminate\Http\Response
     */
    public function edit($uuid)
    {
        $rent = $this->rentRepo->findByUuid($uuid);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $uuid
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $uuid)
    {
        $rent = $this->rentRepo->findByUuid($uuid);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $uuid
     * @return \Illuminate\Http\Response
     */
    public function destroy($uuid)
    {
        $rent = $this->rentRepo->findByUuid($uuid);
    }
}
