<?php
namespace BikeShare\Domain\Core;

use Illuminate\Support\Collection;
use Illuminate\Container\Container as App;
use Bosnadev\Repositories\Eloquent\Repository as CoreRepository;

/**
 * Class Repository
 * @package Bosnadev\Repositories\Eloquent
 */
abstract class Repository extends CoreRepository
{

    protected $app;

    public function __construct(App $app, Collection $collection)
    {
        parent::__construct($app, $collection);
        $this->app = $app;
    }
    /**
     * @param       $uuid
     * @param array $columns
     *
     * @return mixed
     */
    public function findByUuid($uuid, $columns = ['*'])
    {
        return $this->findBy('uuid', $uuid, $columns);
    }


    /**
     * @param       $uuid
     * @param array $columns
     *
     * @return mixed
     */
    public function findByUuidWithTrashed($uuid, $columns = ['*'])
    {
        $this->applyCriteria();

        return $this->model->withTrashed()->where('uuid', '=', $uuid)->first($columns);
    }


    public function generateBikeCode()
    {
        return mt_rand(1010, 9900);
    }
}
