<?php

namespace BikeShare\Http\Controllers\Api\v1\Me;

use BikeShare\Domain\Rent\RentsRepository;
use BikeShare\Domain\Rent\RentTransformer;
use BikeShare\Domain\Stand\Stand;
use BikeShare\Domain\Stand\StandTransformer;
use BikeShare\Domain\User\UserTransformer;
use BikeShare\Http\Controllers\Api\v1\Controller;
use DB;
use Dingo\Api\Http\Request;

class MeController extends Controller
{

    protected $rendsRepo;


    public function __construct(RentsRepository $rentsRepository)
    {
        $this->rendsRepo = $rentsRepository;
    }


    public function getInfo()
    {
        return $this->response->item($this->user, new UserTransformer());
    }


    public function changePassword(Request $request)
    {
        if (! \Hash::check($request->get('old_password'), $this->user->password)) {
            return $this->response->error("The old_password field is not match with actual password.", 422);
        }

        $this->user->password = bcrypt($request->get('new_password'));
        $this->user->save();

        return $this->response->item($this->user, new UserTransformer());
    }


    public function closestStands()
    {
        if (request()->has('longitude') && request()->has('latitude')) {
            $lat = request()->get('latitude');
            $lng = request()->get('longitude');
            $stands = Stand::selectRaw("*, (6371 * acos(cos(radians('$lat')) * cos(radians(latitude)) * cos(radians(longitude) - radians('$lng')) + sin(radians('$lat')) * sin(radians(latitude )))) AS distance")
                ->having('distance', '<', 1)// 1000m or 1km radius
                ->orderBy('distance')
                ->limit(request()->get('count') ?? 10)
                ->get();
        } else {
            $stands = Stand::orderBy('name')->get();
        }

        return $this->response->collection($stands, new StandTransformer());
    }


    public function getAllRents()
    {
        $rents = $this->user->rents()->orderBy('status', 'desc')->get();

        return $this->response->collection($rents, new RentTransformer());
    }


    public function getActiveRents()
    {
        $rents = $this->user->activeRents()->get();

        return $this->response->collection($rents, new RentTransformer());
    }
}
