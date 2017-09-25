<?php

namespace BikeShare\Domain\Core;

use Prettus\Repository\Contracts\CacheableInterface;
use Prettus\Repository\Eloquent\BaseRepository;

/**
 * Class Repository
 * @package Bosnadev\Repositories\Eloquent
 */
abstract class Repository extends BaseRepository implements CacheableInterface
{

    use CacheableRepository;


    public function findByOrFail($field, $value = null, $columns = ['*'])
    {
        $this->applyCriteria();
        $this->applyScope();
        $model = $this->model->where($field, '=', $value)->firstOrFail($columns);
        $this->resetModel();

        return $this->parserResult($model);
    }


    // TODO cache this custom method
    public function findBy($field, $value = null, $columns = ['*'])
    {
        $this->applyCriteria();
        $this->applyScope();
        $model = $this->model->where($field, '=', $value)->first($columns);
        $this->resetModel();

        return $this->parserResult($model);
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
    public function findByUuidOrFail($uuid, $columns = ['*'])
    {
        return $this->findByOrFail('uuid', $uuid, $columns);
    }


    /**
     * @param array  $data
     * @param        $id
     * @param string $attribute
     *
     * @return mixed
     */
    public function update(array $data, $id, $attribute = "id")
    {
        $data = $this->formatData($data);

        return $this->model->where($attribute, '=', $id)->update($data);
    }


    public function withTrashed()
    {
        $this->model = $this->model->withTrashed();

        return $this;
    }


    /**
     * @param       $uuid
     * @param array $columns
     *
     * @return mixed
     */
    public function findByUuidWithTrashed($uuid, $columns = ['*'])
    {
        return $this->withTrashed()->findBy('uuid', '=', $uuid);
    }


    public function generateBikeCode()
    {
        return mt_rand(1010, 9900);
    }


    public function formatData(array $data): array
    {
        if (isset($data['_token'])) {
            unset($data['_token']);
        }
        if (isset($data['_method'])) {
            unset($data['_method']);
        }

        return $data;
    }
}
