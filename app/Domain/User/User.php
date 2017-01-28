<?php

namespace BikeShare\Domain\User;

use BikeShare\Domain\Rent\Rent;
use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Core\Uuid;
use BikeShare\Domain\Rent\RentStatus;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{

    use Notifiable, HasRoles, Uuid, SoftDeletes, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'uuid',
        'name',
        'email',
        'phone_number',
        'password',
        'credit',
        'limit',
        'note',
        'recommendation',
        'confirmation_token',
        'locked',
    ];

    protected static $logAttributes = [
        'name',
        'email',
        'phone_number',
        'password',
        'credit',
        'limit',
        'note',
        'recommendation',
        'locked',
    ];

    protected $dates = ['deleted_at', 'last_login'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];


    public function setCreditAttribute($value)
    {
        $this->attributes['credit'] = app('AppConfig')->getRequiredCredit() * $value;
    }


    public function rents()
    {
        return $this->hasMany(Rent::class);
    }


    public function activeRents()
    {
        return $this->hasMany(Rent::class)->where('status', RentStatus::OPEN);
    }

    public function routeNotificationForMail()
    {
        return $this->email;
    }


    public function bikes()
    {
        return $this->hasMany(Bike::class);
    }


    public function getJWTIdentifier()
    {
        return $this->getKey();
    }


    public function getJWTCustomClaims()
    {
        return [];
    }
}
