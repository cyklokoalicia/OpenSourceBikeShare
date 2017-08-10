<?php

namespace BikeShare\Domain\Sms;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class Sms extends Model
{
    use LogsActivity;

    public $table = 'sms';

    public $fillable = [
        'uuid',
        'incoming',
        'sender',
        'receiver',
        'received_at',
        'sent_at',
        'sms_text',
        'ip'
    ];

    public $dates = ['received_at', 'sent_at'];

    protected static $logAttributes = [
        'uuid',
        'incoming',
        'sender',
        'receiver',
        'received_at',
        'sent_at',
        'sms_text',
        'ip'
    ];

    public $casts = [
        'incoming' => 'boolean'
    ];

}
