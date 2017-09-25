<?php
namespace BikeShare\Domain\Rent;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Core\Model;
use BikeShare\Domain\Stand\Stand;
use BikeShare\Domain\User\User;
use Spatie\Activitylog\Traits\LogsActivity;

class Rent extends Model
{

    use LogsActivity;

    public $table = 'rents';

    public $fillable = ['status', 'method'];

    public $dates = ['deleted_at', 'started_at', 'ended_at'];

    protected static $logAttributes = [
        'status',
    ];


    public function scopeStatus($query, $status)
    {
        $query->where('status', $status);
    }


    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function bike()
    {
        return $this->belongsTo(Bike::class);
    }


    public function standFrom()
    {
        return $this->belongsTo(Stand::class);
    }


    public function standTo()
    {
        return $this->belongsTo(Stand::class);
    }
}
