<?php
namespace BikeShare\Domain\Bike;

use BikeShare\Domain\Core\Model;
use BikeShare\Domain\Note\Note;
use BikeShare\Domain\Rent\Rent;
use BikeShare\Domain\Stand\Stand;
use BikeShare\Domain\User\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class Bike extends Model
{

    use LogsActivity, SoftDeletes;

    public $table = 'bikes';

    public $fillable = ['bike_num', 'current_code', 'status', 'note'];

    protected static $logAttributes = ['bike_num', 'current_code', 'status', 'note'];

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
        return $this->hasMany(Rent::class);
    }


    public function notes()
    {
        return $this->morphMany(Note::class, 'notable');
    }


    public static function generateBikeCode()
    {
        return mt_rand(1010, 9900);
    }

    public function isTopOfStack()
    {
        return $this->stand && $this->stand->getTopPosition() == $this->stack_position;
    }
}
