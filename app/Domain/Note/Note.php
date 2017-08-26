<?php
namespace BikeShare\Domain\Note;

use BikeShare\Domain\Core\Model;
use BikeShare\Domain\User\User;
use Illuminate\Database\Eloquent\SoftDeletes;

class Note extends Model
{
    use SoftDeletes;

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
