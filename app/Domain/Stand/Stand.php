<?php

namespace BikeShare\Domain\Stand;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Core\Model;
use BikeShare\Domain\Note\Note;
use Spatie\Activitylog\Traits\LogsActivity;

class Stand extends Model
{

    use LogsActivity;

    public $table = 'stands';

    public $fillable = ['name', 'descriptions', 'photo', 'place_name', 'service_tag', 'latitude', 'longitude'];

    protected static $logAttributes = [
        'name',
        'descriptions',
        'photo',
        'place_name',
        'service_tag',
        'latitude',
        'longitude',
    ];

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


    public function closest($query, $lat, $lng, $radius)
    {


        return $query->selectRaw("id, (6371 * acos(cos(radians($this->latitude)) * cos($lat)) * cos(radians($lng) - radians($this->longitude)) + sin(radians($this->latitude)) * sin(radians($lat )))) AS distance)")
            ->having('distance', '<', $radius)
            ->orderBy('distance');
    }
}
