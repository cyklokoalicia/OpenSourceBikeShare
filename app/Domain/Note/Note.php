<?php
namespace BikeShare\Domain\Note;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Core\Model;
use BikeShare\Domain\User\User;

class Note extends Model
{

    public $table = 'notes';

    public $fillable = ['note', 'user_id'];

    public $dates = ['deleted_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function notable()
    {
        return $this->morphTo();
    }
}
