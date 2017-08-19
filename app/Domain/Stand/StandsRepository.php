<?php

namespace BikeShare\Domain\Stand;

use BikeShare\Domain\Core\Repository;
use DB;
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

    /**
     * Case-insensitive search
     * @param $name
     * @param array $columns
     * @return Stand
     */
    public function findByStandNameCI($name, $columns = ['*'])
    {
        return $this->findWhere([[DB::raw('UPPER(name)'), '=', mb_strtoupper($name)]], $columns)->first();
    }

    public function model()
    {
        return Stand::class;
    }
}
