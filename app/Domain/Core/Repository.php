<?php
namespace BikeShare\Domain\Core;

use Bosnadev\Repositories\Eloquent\Repository as CoreRepository;

/**
 * Class Repository
 * @package Bosnadev\Repositories\Eloquent
 */
abstract class Repository extends CoreRepository
{

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
