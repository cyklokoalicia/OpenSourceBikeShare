<?php

namespace BikeShare\Domain\Stand;

use BikeShare\Domain\Core\Repository;
use Prettus\Repository\Criteria\RequestCriteria;

class StandsRepository extends Repository
{

    /**
     * @var array
     */
    protected $fieldSearchable = [
        'name' => 'like',
    ];

    public function boot()
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }


    public function model()
    {
        return Stand::class;
    }
}
