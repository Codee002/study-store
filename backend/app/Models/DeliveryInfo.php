<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryInfo extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'address',
        'phone',
        'default',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
