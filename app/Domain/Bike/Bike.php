<?php
namespace BikeShare\Domain\Bike;

use BikeShare\Domain\Core\Model;
use BikeShare\Domain\Note\Note;
use BikeShare\Domain\Stand\Stand;
use BikeShare\Domain\User\User;

class Bike extends Model
{

    public $table = 'bikes';

    public $fillable = ['bike_num', 'current_code', 'status', 'note'];

    public $dates = ['deleted_at'];


    public function stand()
    {
        return $this->belongsTo(Stand::class);
    }


    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function rents()
    {
        return $this->hasMany(Bike::class);
    }


    public function notes()
    {
        return $this->morphMany(Note::class, 'notable');
    }


    public static function generateBikeCode()
    {
        return mt_rand(1010, 9900);
    }
}
