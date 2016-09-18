<?php

namespace BikeShare\Domain\Coupon;

use BikeShare\Domain\Core\Model;
use BikeShare\Domain\User\User;

class Coupon extends Model
{
    public $fillable = ['coupon', 'value', 'user_id', 'status'];

    public $dates = ['deleted_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
