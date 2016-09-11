<?php
namespace BikeShare\Domain\Stand;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Core\Model;
use BikeShare\Domain\Note\Note;

class Stand extends Model
{

    public $table = 'stands';

    public $fillable = ['name', 'descriptions', 'photo', 'place_name', 'service_tag', 'latitude', 'longitude'];

    public $dates = ['deleted_at'];


    public function getTopPosition()
    {
        $bike = $this->bikes()->orderBy('stack_position', 'desc')->first();

        if ($bike) {
            return $bike->stack_position;
        }

        return null;
    }

    public function bikes()
    {
        return $this->hasMany(Bike::class);
    }

    public function notes()
    {
        return $this->morphMany(Note::class, 'notable');
    }
}
