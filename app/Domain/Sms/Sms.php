<?php

namespace BikeShare\Domain\Sms;

use BikeShare\Domain\User\User;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class Sms extends Model
{
    use LogsActivity;

    public $table = 'sms';

    public $fillable = [
        'uuid',
        'incoming',
        'from',
        'to',
        'received_at',
        'sent_at',
        'sms_text',
        'ip'
    ];

    public $dates = ['received_at', 'sent_at'];

    protected static $logAttributes = [
        'uuid',
        'incoming',
        'from',
        'to',
        'received_at',
        'sent_at',
        'sms_text',
        'ip'
    ];

    public $casts = [
        'incoming' => 'boolean'
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'from', 'phone_number');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'to', 'phone_number');
    }

}
