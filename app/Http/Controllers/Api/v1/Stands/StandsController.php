<?php

namespace BikeShare\Http\Controllers\Api\v1\Stands;

use BikeShare\Domain\Stand\Stand;
use BikeShare\Domain\Stand\StandsRepository;
use BikeShare\Domain\Stand\StandTransformer;
use BikeShare\Http\Controllers\Api\v1\Controller;
use Illuminate\Http\Request;

class StandsController extends Controller
{

    protected $standRepo;


    public function __construct(StandsRepository $standsRepository)
    {
        $this->standRepo = $standsRepository;
    }


    public function index()
    {

        $stands = Stand::first();

        dd($stands->getTopPosition());

        //$stands = $this->standRepo->all();

        return $this->response->collection($stands, new StandTransformer());
    }


    public function show($uuid)
    {
        $stand = $this->standRepo->findByUuid($uuid);

        return $this->response->item($stand, new StandTransformer());
    }


    public function import(Request $request)
    {
        foreach ($request->all() as $stand) {
            $data = [
                'description' => $stand["standDescription"] ?? null,
                'name' => $stand["standName"] ?? null,
                'photo' => $stand["standPhoto"] ?? null,
                'latitude' => $stand["lat"] ?? null,
                'longitude' => $stand["lon"] ?? null,
            ];
            $this->standRepo->create($data);
        }
    }
}
