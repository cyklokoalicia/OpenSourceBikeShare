<?php
namespace BikeShare\Domain\Stand;

use BikeShare\Domain\Bike\BikeTransformer;
use League\Fractal\TransformerAbstract;

class StandTransformer extends TransformerAbstract
{

    /**
     * List of resources possible to include
     *
     * @var array
     */
    protected $availableIncludes = [
        'bikes'
    ];


    public function transform(Stand $stand)
    {
        return [
            'uuid'        => (string)$stand->uuid,
            'name'        => $stand->name,
            'latitude'    => $stand->latitude,
            'longitude'   => $stand->longitude,
            'photo'       => $stand->photo,
            'description' => $stand->description,
            'place_name'  => $stand->place_name,
            'service_tag' => $stand->service_tag,
        ];
    }


    public function includeBikes(Stand $stand)
    {
        $bikes = $stand->bikes;

        return $this->collection($bikes, new BikeTransformer);
    }
}
