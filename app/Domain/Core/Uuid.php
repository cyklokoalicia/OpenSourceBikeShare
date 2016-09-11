<?php
namespace BikeShare\Domain\Core;

use Ramsey\Uuid\Uuid as RamseyUuid;

trait Uuid
{
    public static function bootUuid()
    {
        static::creating(function ($model) {
            $model->uuid = (string) RamseyUuid::uuid4();
        });
    }
}
