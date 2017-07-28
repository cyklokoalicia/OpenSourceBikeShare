<?php

namespace BikeShare\Domain\Core;

trait CacheableRepository
{

    use \Prettus\Repository\Traits\CacheableRepository;


    public function findBy($field, $value = null, $columns = ['*'])
    {
        if (! $this->allowedCache('findBy') || $this->isSkippedCache()) {
            return parent::findBy($field, $value, $columns);
        }

        $key = $this->getCacheKey('findBy', func_get_args());
        $minutes = $this->getCacheMinutes();
        $value = $this->getCacheRepository()->remember($key, $minutes, function () use ($field, $value, $columns) {
            return parent::findBy($field, $value, $columns);
        });

        return $value;
    }
}
