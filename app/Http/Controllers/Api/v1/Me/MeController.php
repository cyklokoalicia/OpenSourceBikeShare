<?php
namespace BikeShare\Http\Controllers\Api\v1\Me;

use BikeShare\Domain\Rent\RentsRepository;
use BikeShare\Domain\Rent\RentTransformer;
use BikeShare\Domain\User\UserTransformer;
use BikeShare\Http\Controllers\Api\v1\Controller;

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


    public function getAllRents()
    {
        $rents = $this->user->rents()->get();

        return $this->response->collection($rents, new RentTransformer());
    }

    public function getActiveRents()
    {
        $rents = $this->user->activeRents()->get();

        return $this->response->collection($rents, new RentTransformer());
    }
}
