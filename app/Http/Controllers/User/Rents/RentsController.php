<?php

namespace BikeShare\Http\Controllers\User\Rents;

use BikeShare\Domain\Rent\RentsRepository;
use BikeShare\Http\Controllers\Controller;

class RentsController extends Controller
{

    /**
     * @var RentsRepository
     */
    private $rentsRepository;


    /**
     * RentsController constructor.
     *
     * @param RentsRepository $rentsRepository
     */
    public function __construct(RentsRepository $rentsRepository)
    {
        $this->rentsRepository = $rentsRepository;
    }


    public function show($uuid)
    {
        $rent = $this->rentsRepository->with(['bikes'])->findByUuidOrFail($uuid);

        return view('')->with([
            'rent' => $rent,
        ]);
    }
}
