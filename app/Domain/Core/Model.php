<?php

namespace BikeShare\Domain\Core;

use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * BikeShare\Domain\Core\Model
 *
 * @mixin \Eloquent
 */
class Model extends Eloquent
{
    use SoftDeletes, Uuid;
}
